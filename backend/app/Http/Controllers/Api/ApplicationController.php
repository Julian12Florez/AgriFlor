<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationProduct;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Location;
use App\Models\FarmLot;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Listar aplicaciones con paginación y filtros
     *
     * GET /api/applications
     */
    public function index(Request $request)
    {
        try {
            $query = Application::query();

            // Filtro por ubicación origen
            if ($request->filled('location_id')) {
                $query->where('origin_location_id', $request->location_id);
            }

            // Filtro por lote de finca
            if ($request->filled('farm_lot_id')) {
                $query->where('farm_lot_id', $request->farm_lot_id);
            }

            // Filtro por estado
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filtro por rango de fechas
            if ($request->filled('date_from')) {
                $query->where('application_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('application_date', '<=', $request->date_to);
            }

            // Búsqueda por número de aplicación
            if ($request->filled('search')) {
                $query->where('application_number', 'like', '%' . $request->search . '%');
            }

            // Cargar relaciones
            $query->with([
                'originLocation:id,name,type',
                'farmLot:id,name,area',
                'appliedByUser:id,name,email',
                'approvedByUser:id,name,email',
                'applicationProducts.product:id,name,product_code',
                'applicationProducts.brand:id,name'
            ]);

            // Ordenar por fecha de aplicación descendente
            $query->orderBy('application_date', 'desc')
                  ->orderBy('created_at', 'desc');

            // Paginación
            $perPage = $request->get('per_page', 15);
            $applications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $applications
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al listar aplicaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al listar aplicaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de una aplicación
     *
     * GET /api/applications/{id}
     */
    public function show(string $id)
    {
        try {
            $application = Application::with([
                'originLocation',
                'farmLot',
                'appliedByUser',
                'approvedByUser',
                'cancelledByUser',
                'applicationProducts.product',
                'applicationProducts.brand',
                'applicationProducts.reception',
                'inventoryMovements'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $application
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Aplicación no encontrada'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al obtener aplicación', [
                'application_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener aplicación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva aplicación
     *
     * POST /api/applications
     */
    public function store(Request $request)
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'origin_location_id' => 'required|uuid|exists:locations,id',
                'farm_lot_id' => 'required|uuid|exists:farm_lots,id',
                'application_date' => 'required|date',
                'application_type' => 'nullable|string|max:100',
                'observations' => 'nullable|string',
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
            ], [
                'origin_location_id.required' => 'La ubicación origen es requerida',
                'origin_location_id.exists' => 'La ubicación origen no existe',
                'farm_lot_id.required' => 'El lote de finca es requerido',
                'farm_lot_id.exists' => 'El lote de finca no existe',
                'application_date.required' => 'La fecha de aplicación es requerida',
                'application_date.date' => 'La fecha de aplicación debe ser una fecha válida',
                'products.required' => 'Debe agregar al menos un producto',
                'products.*.product_id.required' => 'El producto es requerido',
                'products.*.product_id.exists' => 'El producto no existe',
                'products.*.brand_id.required' => 'La marca es requerida',
                'products.*.brand_id.exists' => 'La marca no existe',
                'products.*.quantity.required' => 'La cantidad es requerida',
                'products.*.quantity.min' => 'La cantidad debe ser mayor a 0',
                'products.*.unit.required' => 'La unidad es requerida',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Validar stock disponible antes de crear la aplicación
            // ERR-004 fix: convert to base unit before comparing
            // ERR-006 fix: use lockForUpdate to prevent race conditions during validation
            foreach ($request->products as $productData) {
                $inventoryBatches = Inventory::lockForUpdate()
                    ->where('product_id', $productData['product_id'])
                    ->where('brand_id', $productData['brand_id'])
                    ->where('location_id', $request->origin_location_id)
                    ->whereNotIn('status', ['expired'])
                    ->where('quantity', '>', 0)
                    ->get();

                $availableInBase = 0;
                foreach ($inventoryBatches as $batch) {
                    $availableInBase += $this->inventoryService->toBaseUnit(
                        floatval($batch->quantity),
                        $batch->unit,
                        $productData['product_id']
                    );
                }

                $requestedInBase = $this->inventoryService->toBaseUnit(
                    floatval($productData['quantity']),
                    $productData['unit'],
                    $productData['product_id']
                );

                if ($availableInBase < $requestedInBase - 0.01) {
                    $product = Product::find($productData['product_id']);
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuficiente para el producto {$product->name}. Disponible: " . round($availableInBase, 2) . " unidades base, Solicitado: " . round($requestedInBase, 2) . " unidades base"
                    ], 400);
                }
            }

            // Generar número de aplicación
            $applicationNumber = Application::generateApplicationNumber();

            // Crear aplicación
            $application = Application::create([
                'application_number' => $applicationNumber,
                'origin_location_id' => $request->origin_location_id,
                'farm_lot_id' => $request->farm_lot_id,
                'application_date' => $request->application_date,
                'applied_by' => auth()->id(),
                'status' => 'pending',
                'application_type' => $request->application_type,
                'observations' => $request->observations,
            ]);

            // Crear productos de la aplicación
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
                    'observations' => $productData['observations'] ?? null,
                ]);
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $application->load([
                'originLocation',
                'farmLot',
                'appliedByUser',
                'applicationProducts.product',
                'applicationProducts.brand'
            ]);

            Log::info('Aplicación creada exitosamente', [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aplicación creada exitosamente',
                'data' => $application
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear aplicación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear aplicación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar aplicación y descontar stock usando FIFO
     * Uses pessimistic locking to prevent race conditions (ERR-005 fix)
     *
     * POST /api/applications/{id}/approve
     */
    public function approve(string $id)
    {
        try {
            $application = Application::with('applicationProducts.product')->findOrFail($id);

            // Validar que la aplicación pueda ser aprobada
            if (!$application->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La aplicación no puede ser aprobada. Debe estar en estado pendiente y tener productos.'
                ], 400);
            }

            DB::beginTransaction();

            // Validar y bloquear stock con pessimistic locking (ERR-005 fix)
            // ERR-004 fix: convert to base unit before comparing
            foreach ($application->applicationProducts as $appProduct) {
                // Lock the inventory rows BEFORE checking availability
                $inventoryBatches = Inventory::lockForUpdate()
                    ->where('product_id', $appProduct->product_id)
                    ->where('brand_id', $appProduct->brand_id)
                    ->where('location_id', $application->origin_location_id)
                    ->whereNotIn('status', ['expired'])
                    ->where('quantity', '>', 0)
                    ->get();

                $availableInBase = 0;
                foreach ($inventoryBatches as $batch) {
                    $availableInBase += $this->inventoryService->toBaseUnit(
                        floatval($batch->quantity),
                        $batch->unit,
                        $appProduct->product_id
                    );
                }

                $requestedInBase = $this->inventoryService->toBaseUnit(
                    floatval($appProduct->quantity),
                    $appProduct->unit,
                    $appProduct->product_id
                );

                if ($availableInBase < $requestedInBase - 0.01) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuficiente para el producto {$appProduct->product->name}. Disponible: " . round($availableInBase, 2) . " unidades base, Requerido: " . round($requestedInBase, 2) . " unidades base"
                    ], 400);
                }
            }

            // Descontar stock usando FIFO para cada producto (now safe from race conditions)
            foreach ($application->applicationProducts as $appProduct) {
                // Reducir inventario usando FIFO con conversion de unidades
                // ERR-002 fix: uses InventoryService to handle unit conversion
                $this->inventoryService->reduceInventoryFIFO(
                    $appProduct->product_id,
                    $appProduct->brand_id,
                    $application->origin_location_id,
                    $appProduct->quantity,
                    $appProduct->unit
                );

                // Crear movimiento de inventario
                InventoryMovement::create([
                    'type' => 'exit',
                    'product_id' => $appProduct->product_id,
                    'brand_id' => $appProduct->brand_id,
                    'location_id' => $application->origin_location_id,
                    'quantity' => $appProduct->quantity,
                    'unit' => $appProduct->unit,
                    'movement_date' => $application->application_date ?: now()->toDateString(),
                    'responsible_user' => auth()->id(),
                    'related_document_type' => 'App\Models\Application',
                    'related_document_id' => $application->id,
                    'observations' => "Aplicación {$application->application_number} en lote {$application->farmLot->name}",
                ]);

                Log::info('Stock descontado por aplicación (FIFO)', [
                    'application_id' => $application->id,
                    'product_id' => $appProduct->product_id,
                    'brand_id' => $appProduct->brand_id,
                    'location_id' => $application->origin_location_id,
                    'quantity' => $appProduct->quantity
                ]);
            }

            // Actualizar estado de la aplicación
            $application->status = 'approved';
            $application->approved_by = auth()->id();
            $application->approved_at = now();
            $application->save();

            DB::commit();

            // Recargar la aplicación con todas sus relaciones
            $application->load([
                'originLocation',
                'farmLot',
                'appliedByUser',
                'approvedByUser',
                'applicationProducts.product',
                'applicationProducts.brand',
                'inventoryMovements'
            ]);

            Log::info('Aplicación aprobada exitosamente', [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'approved_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aplicación aprobada exitosamente y stock descontado',
                'data' => $application
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Aplicación no encontrada'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al aprobar aplicación', [
                'application_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar aplicación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar aplicación
     *
     * POST /api/applications/{id}/cancel
     */
    public function cancel(Request $request, string $id)
    {
        try {
            $application = Application::findOrFail($id);

            // Validar que la aplicación pueda ser cancelada
            if (!$application->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden cancelar aplicaciones en estado pendiente'
                ], 400);
            }

            // Validar motivo de cancelación
            $validator = Validator::make($request->all(), [
                'cancellation_reason' => 'required|string|max:500'
            ], [
                'cancellation_reason.required' => 'El motivo de cancelación es requerido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $application->status = 'cancelled';
            $application->cancelled_by = auth()->id();
            $application->cancelled_at = now();
            $application->cancellation_reason = $request->cancellation_reason;
            $application->save();

            DB::commit();

            $application->load([
                'originLocation',
                'farmLot',
                'appliedByUser',
                'cancelledByUser',
                'applicationProducts.product',
                'applicationProducts.brand'
            ]);

            Log::info('Aplicación cancelada', [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'cancelled_by' => auth()->id(),
                'reason' => $request->cancellation_reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aplicación cancelada exitosamente',
                'data' => $application
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Aplicación no encontrada'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al cancelar aplicación', [
                'application_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar aplicación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // NOTE: reduceInventoryFIFO has been moved to App\Services\InventoryService
    // with unit conversion support (ERR-002 fix)
}
