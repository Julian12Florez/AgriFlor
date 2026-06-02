<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductOutputRequest;
use App\Http\Requests\UpdateProductOutputRequest;
use App\Http\Resources\ProductOutputResource;
use App\Models\Application;
use App\Models\ApplicationProduct;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\OutputProduct;
use App\Models\ProductOutput;
use App\Models\Reception;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductOutputController extends Controller
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of product outputs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductOutput::query()
            ->with([
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser',
                'outputProducts.product',
                'outputProducts.brand',
                'outputType',
                'farmLots.location'
            ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by origin location
        if ($request->has('origin_location_id')) {
            $query->where('origin_location_id', $request->origin_location_id);
        }

        // Filter by destination location
        if ($request->has('destination_location_id')) {
            $query->where('destination_location_id', $request->destination_location_id);
        }

        // Filter by technical order
        if ($request->has('technical_order_id')) {
            $query->where('technical_order_id', $request->technical_order_id);
        }

        // Date range filter
        if ($request->has('start_date')) {
            $query->where('output_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('output_date', '<=', $request->end_date);
        }

        // Search by output_number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('output_number', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        $outputs = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return ProductOutputResource::collection($outputs);
    }

    /**
     * Store a newly created product output
     */
    public function store(StoreProductOutputRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $products = $data['products'];
            unset($data['products']);

            // Validar stock disponible REAL (físico - comprometido por otras salidas pendientes)
            // antes de crear la salida. Evita comprometer stock que ya está en tránsito.
            // Solo se cuentan estados approved/in_transit/partial (alineado con approve()).
            // Las salidas 'pending' aún no están aprobadas y no deben bloquear stock.
            $originLocationId = $data['origin_location_id'];
            $otherCommittedOutputs = ProductOutput::where('origin_location_id', $originLocationId)
                ->whereIn('status', ['approved', 'in_transit', 'partial'])
                ->get();

            foreach ($products as $productData) {
                $productId = $productData['product_id'];
                $brandId = $productData['brand_id'];
                $unit = $productData['unit'];
                $requestedQty = floatval($productData['quantity_delivered']);

                // Stock físico
                $physicalQty = Inventory::where('location_id', $originLocationId)
                    ->where('product_id', $productId)
                    ->where('brand_id', $brandId)
                    ->sum('quantity');

                // Convertir a base
                $physicalBase = $this->inventoryService->toBaseUnit($physicalQty, $unit, $productId);
                $requestedBase = $this->inventoryService->toBaseUnit($requestedQty, $unit, $productId);

                // Calcular comprometido por otras salidas (no recibidas)
                // FIX: usar 'output_id' (FK real en OutputProduct), no 'product_output_id'
                $committedBase = 0;
                $blockingOutputs = []; // salidas que retienen este stock (para el mensaje de error)
                foreach ($otherCommittedOutputs as $otherOutput) {
                    $otherProducts = OutputProduct::where('output_id', $otherOutput->id)
                        ->where('product_id', $productId)
                        ->where('brand_id', $brandId)
                        ->get();

                    foreach ($otherProducts as $otherProduct) {
                        $deliveredBase = $this->inventoryService->toBaseUnit(
                            floatval($otherProduct->quantity_delivered),
                            $otherProduct->unit,
                            $productId
                        );
                        // Restar lo ya recibido
                        $receivedBase = 0;
                        $reception = Reception::where('source_id', $otherOutput->id)
                            ->where('source_type', 'output')->first();
                        if ($reception) {
                            $items = $reception->receptionItems()
                                ->where('product_id', $productId)
                                ->where('brand_id', $brandId)
                                ->get();
                            foreach ($items as $item) {
                                $receivedBase += $this->inventoryService->toBaseUnit(
                                    floatval($item->quantity_received), $item->unit, $productId
                                );
                            }
                        }
                        $pendingBase = max(0, $deliveredBase - $receivedBase);
                        if ($pendingBase > 0.01) {
                            $committedBase += $pendingBase;
                            $blockingOutputs[] = ($otherOutput->output_number ?? $otherOutput->id)
                                . ' (' . round($pendingBase, 2) . ')';
                        }
                    }
                }

                $availableBase = $physicalBase - $committedBase;
                if ($availableBase < $requestedBase - 0.01) {
                    DB::rollBack();
                    $product = \App\Models\Product::find($productId);
                    $productName = $product?->name ?? 'producto';
                    $blockingMsg = !empty($blockingOutputs)
                        ? " | Retenido por: " . implode(', ', $blockingOutputs)
                        : '';
                    $msg = "Stock insuficiente para {$productName}. Físico: " . round($physicalBase, 2)
                        . " | Comprometido en otras salidas pendientes: " . round($committedBase, 2)
                        . " | Disponible real: " . round($availableBase, 2)
                        . " | Solicitado: " . round($requestedBase, 2)
                        . $blockingMsg;
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                    ], 422);
                }
            }

            // Generate output number automatically
            $data['output_number'] = ProductOutput::generateOutputNumber();

            // Add responsible user
            $data['responsible_user'] = Auth::id();
            $data['status'] = 'pending';

            // Create the output
            $output = ProductOutput::create($data);

            // Calculate total cost and create output products
            $totalCost = 0;

            foreach ($products as $productData) {
                // Calculate cost and get expiration date from inventory
                // NOTE: Expired products are included — users need to be able to dispose of them
                $inventoryQuery = Inventory::where('location_id', $output->origin_location_id)
                    ->where('product_id', $productData['product_id'])
                    ->where('brand_id', $productData['brand_id']);

                if (isset($productData['batch_number'])) {
                    $inventoryQuery->where('batch_number', $productData['batch_number']);
                }

                $inventory = $inventoryQuery->where('quantity', '>', 0)
                    ->orderBy('expiration_date', 'asc')
                    ->first();

                // Store expiration_date from inventory if not provided
                if (!isset($productData['expiration_date']) && $inventory?->expiration_date) {
                    $productData['expiration_date'] = $inventory->expiration_date;
                }

                // Create output product
                $outputProduct = $output->outputProducts()->create($productData);

                if ($inventory) {
                    $cost = $inventory->unit_price * $productData['quantity_delivered'];
                    $totalCost += $cost;
                }
            }

            // Update total cost
            $output->update(['total_cost' => $totalCost]);

            // Attach farm lots if provided (for consumption type outputs)
            if (isset($data['farm_lot_ids']) && is_array($data['farm_lot_ids'])) {
                $output->farmLots()->sync($data['farm_lot_ids']);
            }

            // Load relationships
            $output->load([
                'outputProducts.product',
                'outputProducts.brand',
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser',
                'outputType',
                'farmLots.location'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos creada exitosamente',
                'data' => new ProductOutputResource($output)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la salida de productos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product output
     */
    public function show(string $id): JsonResponse
    {
        $output = ProductOutput::with([
            'outputProducts.product',
            'outputProducts.brand',
            'originLocation',
            'destinationLocation',
            'technicalOrder',
            'responsibleUser',
            'outputType',
            'farmLots.location'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ProductOutputResource($output)
        ]);
    }

    /**
     * Update the specified product output
     */
    public function update(UpdateProductOutputRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $output = ProductOutput::findOrFail($id);

            // Check if output can be updated
            if ($output->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede actualizar una salida completada'
                ], 422);
            }

            $data = $request->validated();
            $products = $data['products'] ?? null;
            $farmLotIds = $data['farm_lot_ids'] ?? null;
            unset($data['products']);
            unset($data['farm_lot_ids']);

            // Update output
            $output->update($data);

            // Update products if provided
            if ($products !== null) {
                // Delete existing products
                $output->outputProducts()->delete();

                // Calculate total cost and create new output products
                $totalCost = 0;

                foreach ($products as $productData) {
                    // Calculate cost and get expiration date from inventory
                    // NOTE: Expired products included — users need to dispose of them
                    $inventoryQuery = Inventory::where('location_id', $output->origin_location_id)
                        ->where('product_id', $productData['product_id'])
                        ->where('brand_id', $productData['brand_id']);

                    if (isset($productData['batch_number'])) {
                        $inventoryQuery->where('batch_number', $productData['batch_number']);
                    }

                    $inventory = $inventoryQuery->where('quantity', '>', 0)
                        ->orderBy('expiration_date', 'asc')
                        ->first();

                    // Store expiration_date from inventory if not provided
                    if (!isset($productData['expiration_date']) && $inventory?->expiration_date) {
                        $productData['expiration_date'] = $inventory->expiration_date;
                    }

                    // Create output product
                    $outputProduct = $output->outputProducts()->create($productData);

                    if ($inventory) {
                        $cost = $inventory->unit_price * $productData['quantity_delivered'];
                        $totalCost += $cost;
                    }
                }

                // Update total cost
                $output->update(['total_cost' => $totalCost]);
            }

            // Update farm lots if provided
            if ($farmLotIds !== null) {
                $output->farmLots()->sync($farmLotIds);
            }

            // Load relationships
            $output->load([
                'outputProducts.product',
                'outputProducts.brand',
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser',
                'outputType',
                'farmLots.location'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos actualizada exitosamente',
                'data' => new ProductOutputResource($output)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la salida de productos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product output
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $output = ProductOutput::findOrFail($id);

            // Only allow deletion if status is pending
            if ($output->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar salidas con estado pendiente'
                ], 422);
            }

            // Delete output products
            $output->outputProducts()->delete();

            // Delete output
            $output->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la salida de productos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve the product output
     * Requires supervisor role
     * Uses pessimistic locking to validate stock (ERR-003 fix)
     */
    public function approve(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $output = ProductOutput::with(['outputProducts.product', 'outputType'])->findOrFail($id);

            // Check if output is pending
            if ($output->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden aprobar salidas con estado pendiente'
                ], 422);
            }

            // Check user role (assuming there's a role check middleware or method)
            $user = Auth::user();
            if (!$user->hasRole('supervisor') && !$user->hasRole('admin')) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para aprobar salidas'
                ], 403);
            }

            // Validate inventory availability with lock BEFORE approving (ERR-005 fix)
            // This prevents over-approving when multiple outputs compete for same stock
            // ERR-004 fix: convert inventory quantities to base unit before comparing
            // LOG-002 fix: discount committed inventory from other approved/partial outputs

            // Get other approved/partial outputs from the same origin (cached outside product loop)
            $otherApprovedOutputs = ProductOutput::where('origin_location_id', $output->origin_location_id)
                ->whereIn('status', ['approved', 'partial'])
                ->where('id', '!=', $output->id)
                ->get();

            foreach ($output->outputProducts as $outputProduct) {
                // NOTE: Expired products included — users need to dispose of them via outputs
                $inventoryBatches = Inventory::lockForUpdate()
                    ->where('product_id', $outputProduct->product_id)
                    ->where('brand_id', $outputProduct->brand_id)
                    ->where('location_id', $output->origin_location_id)
                    ->where('quantity', '>', 0)
                    ->get();

                // Convert all inventory to base unit for uniform comparison
                $availableInBase = 0;
                foreach ($inventoryBatches as $batch) {
                    $availableInBase += $this->inventoryService->toBaseUnit(
                        floatval($batch->quantity),
                        $batch->unit,
                        $outputProduct->product_id
                    );
                }

                // Calculate committed inventory from other approved/partial outputs
                // FIX: usar 'output_id' (FK real en OutputProduct), no 'product_output_id'
                $committedInBase = 0;
                foreach ($otherApprovedOutputs as $otherOutput) {
                    $otherProducts = OutputProduct::where('output_id', $otherOutput->id)
                        ->where('product_id', $outputProduct->product_id)
                        ->where('brand_id', $outputProduct->brand_id)
                        ->get();

                    foreach ($otherProducts as $otherProduct) {
                        $deliveredInBase = $this->inventoryService->toBaseUnit(
                            floatval($otherProduct->quantity_delivered),
                            $otherProduct->unit,
                            $otherProduct->product_id
                        );

                        // Subtract already received quantities (already reduced from inventory)
                        $receivedInBase = 0;
                        $reception = Reception::where('source_id', $otherOutput->id)
                            ->where('source_type', 'output')
                            ->first();

                        if ($reception) {
                            $receptionItems = $reception->receptionItems()
                                ->where('product_id', $otherProduct->product_id)
                                ->where('brand_id', $otherProduct->brand_id)
                                ->get();

                            foreach ($receptionItems as $receptionItem) {
                                $receivedInBase += $this->inventoryService->toBaseUnit(
                                    floatval($receptionItem->quantity_received),
                                    $receptionItem->unit,
                                    $otherProduct->product_id
                                );
                            }
                        }

                        $committedInBase += max(0, $deliveredInBase - $receivedInBase);
                    }
                }

                // Effective available = physical inventory - committed by other outputs
                $effectiveAvailable = $availableInBase - $committedInBase;

                // Convert requested quantity to base unit
                $requestedInBase = $this->inventoryService->toBaseUnit(
                    floatval($outputProduct->quantity_delivered),
                    $outputProduct->unit,
                    $outputProduct->product_id
                );

                if ($effectiveAvailable < $requestedInBase - 0.01) {
                    DB::rollBack();
                    $product = $outputProduct->product;
                    $committedMsg = $committedInBase > 0
                        ? " (Comprometido por otras salidas: " . round($committedInBase, 2) . ")"
                        : "";
                    return response()->json([
                        'success' => false,
                        'message' => "Inventario insuficiente para {$product->name}. Disponible: " . round($effectiveAvailable, 2) . " unidades base, Requerido: " . round($requestedInBase, 2) . " unidades base{$committedMsg}"
                    ], 400);
                }
            }

            // Update status to approved
            // NOTE: Inventory will be reduced gradually as receptions are processed
            // This allows partial receptions to reduce inventory incrementally
            $output->update(['status' => 'approved']);

            \Log::info('ProductOutput approved - inventory will be reduced during reception', [
                'output_id' => $output->id,
                'output_number' => $output->output_number,
                'origin_location_id' => $output->origin_location_id,
            ]);

            $output->load([
                'outputProducts.product',
                'outputProducts.brand',
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos aprobada exitosamente',
                'data' => new ProductOutputResource($output)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Error al aprobar salida de productos', [
                'output_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la salida de productos',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    /**
     * Mark the output as in transit
     * Uses transaction for data consistency (ERR-009 fix)
     */
    public function markInTransit(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $output = ProductOutput::findOrFail($id);

            // Check if output is approved
            if ($output->status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden marcar en tránsito salidas aprobadas'
                ], 422);
            }

            // Update status
            $output->update(['status' => 'in_transit']);

            DB::commit();

            $output->load([
                'outputProducts.product',
                'outputProducts.brand',
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salida marcada en tránsito exitosamente',
                'data' => new ProductOutputResource($output)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Error al marcar salida en tránsito', [
                'output_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la salida en tránsito'
            ], 500);
        }
    }

    /**
     * Mark the output as completed
     * Uses transaction for data consistency (ERR-010 fix)
     */
    public function complete(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $output = ProductOutput::findOrFail($id);

            // Check if output is in transit or approved
            if (!in_array($output->status, ['approved', 'in_transit'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden completar salidas aprobadas o en tránsito'
                ], 422);
            }

            // Update status
            $output->update(['status' => 'completed']);

            DB::commit();

            $output->load([
                'outputProducts.product',
                'outputProducts.brand',
                'originLocation',
                'destinationLocation',
                'technicalOrder',
                'responsibleUser'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salida completada exitosamente',
                'data' => new ProductOutputResource($output)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Error al completar salida', [
                'output_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al completar la salida'
            ], 500);
        }
    }

    /**
     * Validate inventory availability before creating an output
     * ERR-004a fix: converts units to base before comparing
     * INC-001 fix: uses whereNotIn('status', ['expired']) instead of where('status', 'good')
     */
    public function validateInventory(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|uuid|exists:locations,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|uuid|exists:products,id',
            'products.*.brand_id' => 'required|uuid|exists:brands,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit' => 'nullable|string',
            'allow_expired' => 'nullable|boolean',
        ]);

        $results = [];
        $allValid = true;

        foreach ($request->products as $productData) {
            // NOTE: Expired products included — users need to dispose of them via outputs
            $inventoryBatches = Inventory::where('product_id', $productData['product_id'])
                ->where('brand_id', $productData['brand_id'])
                ->where('location_id', $request->location_id)
                ->where('quantity', '>', 0)
                ->orderBy('expiration_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $product = \App\Models\Product::find($productData['product_id']);
            $brand = \App\Models\Brand::find($productData['brand_id']);

            // Convert all inventory to base unit
            $availableInBase = 0;
            foreach ($inventoryBatches as $batch) {
                $availableInBase += $this->inventoryService->toBaseUnit(
                    floatval($batch->quantity),
                    $batch->unit,
                    $productData['product_id']
                );
            }

            // Convert requested quantity to base unit
            $requestedUnit = $productData['unit'] ?? $product->base_unit ?? 'kg';
            $requestedInBase = $this->inventoryService->toBaseUnit(
                floatval($productData['quantity']),
                $requestedUnit,
                $productData['product_id']
            );

            $sufficient = $availableInBase >= $requestedInBase - 0.01;

            if (!$sufficient) {
                $allValid = false;
            }

            $batches = $inventoryBatches->map(function ($inv) {
                return [
                    'inventory_id' => $inv->id,
                    'quantity' => $inv->quantity,
                    'unit' => $inv->unit,
                    'expiration_date' => $inv->expiration_date,
                    'days_to_expiry' => $inv->expiration_date
                        ? now()->diffInDays($inv->expiration_date, false)
                        : null,
                ];
            });

            $results[] = [
                'product_id' => $productData['product_id'],
                'product_name' => $product ? $product->name : 'Desconocido',
                'brand_id' => $productData['brand_id'],
                'brand_name' => $brand ? $brand->name : 'Desconocido',
                'requested' => $productData['quantity'],
                'requested_unit' => $requestedUnit,
                'available_base' => round($availableInBase, 2),
                'requested_base' => round($requestedInBase, 2),
                'sufficient' => $sufficient,
                'deficit_base' => $sufficient ? 0 : round($requestedInBase - $availableInBase, 2),
                'batches' => $batches,
                'message' => $sufficient
                    ? 'Inventario suficiente'
                    : "Faltan " . round($requestedInBase - $availableInBase, 2) . " unidades base",
            ];
        }

        return response()->json([
            'success' => true,
            'valid' => $allValid,
            'message' => $allValid
                ? 'Inventario suficiente para todos los productos'
                : 'Inventario insuficiente para algunos productos',
            'data' => $results,
        ]);
    }

    /**
     * Registrar aplicacion de productos desde una salida tipo consumo
     *
     * Este endpoint permite registrar los detalles de la aplicacion
     * despues de que los productos han sido recepcionados en el destino.
     * El inventario NO se descuenta aqui (ya fue descontado en la recepcion).
     *
     * POST /api/product-outputs/{id}/register-application
     */
    public function registerApplication(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'farm_lot_id' => 'required|uuid|exists:farm_lots,id',
            'application_date' => 'required|date',
            'applied_by' => 'required|uuid|exists:users,id',
            'application_type' => 'nullable|string|max:100',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|uuid|exists:products,id',
            'products.*.brand_id' => 'required|uuid|exists:brands,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit' => 'required|string|max:50',
            'products.*.applied_area' => 'nullable|numeric|min:0',
            'products.*.area_unit' => 'nullable|string|max:20',
            'products.*.dosage' => 'nullable|numeric|min:0',
            'products.*.dosage_unit' => 'nullable|string|max:50',
            'products.*.observations' => 'nullable|string',
            'observations' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $output = ProductOutput::with(['outputType', 'farmLots', 'reception'])
                ->findOrFail($id);

            // Validar que sea tipo consumo
            if ($output->outputType?->code !== 'consumption') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden registrar aplicaciones para salidas tipo consumo'
                ], 400);
            }

            // Validar que la salida este recepcionada (partial o completed)
            if (!in_array($output->status, ['partial', 'completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'La salida debe tener recepcion iniciada para registrar aplicaciones'
                ], 400);
            }

            // Validar que el lote pertenece a esta salida
            if (!$output->farmLots->contains('id', $request->farm_lot_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El lote seleccionado no esta asociado a esta salida'
                ], 400);
            }

            // Crear Application
            $application = Application::create([
                'application_number' => Application::generateApplicationNumber(),
                'origin_location_id' => $output->origin_location_id,
                'farm_lot_id' => $request->farm_lot_id,
                'product_output_id' => $output->id,
                'application_date' => $request->application_date,
                'applied_by' => $request->applied_by,
                'status' => 'approved', // Ya se desconto el inventario en la recepcion
                'application_type' => $request->application_type ?? 'consumo_salida',
                'observations' => $request->observations,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Crear ApplicationProducts
            foreach ($request->products as $productData) {
                ApplicationProduct::create([
                    'application_id' => $application->id,
                    'product_id' => $productData['product_id'],
                    'brand_id' => $productData['brand_id'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'applied_area' => $productData['applied_area'] ?? null,
                    'area_unit' => $productData['area_unit'] ?? 'ha',
                    'dosage' => $productData['dosage'] ?? null,
                    'dosage_unit' => $productData['dosage_unit'] ?? null,
                    'reception_id' => $output->reception?->id,
                    'observations' => $productData['observations'] ?? null,
                ]);
            }

            // NOTA: NO se crea InventoryMovement porque el inventario
            // ya fue descontado en la recepcion de la salida

            DB::commit();

            $application->load([
                'applicationProducts.product',
                'applicationProducts.brand',
                'farmLot',
                'appliedByUser',
                'productOutput'
            ]);

            \Log::info('Aplicacion registrada desde salida tipo consumo', [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'output_id' => $output->id,
                'output_number' => $output->output_number,
                'farm_lot_id' => $request->farm_lot_id,
                'products_count' => count($request->products),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aplicacion registrada exitosamente',
                'data' => [
                    'application' => $application,
                    'output' => new ProductOutputResource($output->fresh(['applications', 'farmLots'])),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al registrar aplicacion desde salida', [
                'output_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar aplicacion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener aplicaciones registradas para una salida
     *
     * GET /api/product-outputs/{id}/applications
     */
    public function getApplications(string $id): JsonResponse
    {
        $output = ProductOutput::with([
            'applications.applicationProducts.product',
            'applications.applicationProducts.brand',
            'applications.farmLot',
            'applications.appliedByUser',
            'applications.approvedByUser'
        ])->findOrFail($id);

        // Verificar que sea tipo consumo
        if ($output->outputType?->code !== 'consumption') {
            return response()->json([
                'success' => true,
                'message' => 'Esta salida no es tipo consumo, no tiene aplicaciones asociadas',
                'data' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $output->applications->map(function ($application) {
                return [
                    'id' => $application->id,
                    'application_number' => $application->application_number,
                    'application_date' => $application->application_date->format('Y-m-d'),
                    'application_type' => $application->application_type,
                    'status' => $application->status,
                    'observations' => $application->observations,
                    'farm_lot' => $application->farmLot ? [
                        'id' => $application->farmLot->id,
                        'name' => $application->farmLot->name,
                        'area' => $application->farmLot->area,
                        'area_unit' => $application->farmLot->area_unit,
                    ] : null,
                    'applied_by' => $application->appliedByUser ? [
                        'id' => $application->appliedByUser->id,
                        'name' => $application->appliedByUser->name,
                    ] : null,
                    'approved_by' => $application->approvedByUser ? [
                        'id' => $application->approvedByUser->id,
                        'name' => $application->approvedByUser->name,
                    ] : null,
                    'approved_at' => $application->approved_at?->format('Y-m-d H:i:s'),
                    'products' => $application->applicationProducts->map(function ($ap) {
                        return [
                            'id' => $ap->id,
                            'product_id' => $ap->product_id,
                            'product_name' => $ap->product?->name,
                            'brand_id' => $ap->brand_id,
                            'brand_name' => $ap->brand?->name,
                            'quantity' => $ap->quantity,
                            'unit' => $ap->unit,
                            'applied_area' => $ap->applied_area,
                            'area_unit' => $ap->area_unit,
                            'dosage' => $ap->dosage,
                            'dosage_unit' => $ap->dosage_unit,
                            'observations' => $ap->observations,
                        ];
                    }),
                    'total_products' => $application->applicationProducts->count(),
                    'total_area_applied' => $application->applicationProducts->sum('applied_area'),
                    'created_at' => $application->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }
}
