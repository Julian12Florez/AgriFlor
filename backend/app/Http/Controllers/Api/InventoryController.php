<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryMovementResource;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    /**
     * Tipo de documento con el que se guardan los movimientos que genera la
     * aprobación de una solicitud de ajuste (espejo de
     * AdjustmentController::MOVEMENT_DOCUMENT_TYPE: el nombre de clase completo,
     * NO el alias del morph map).
     */
    private const ADJUSTMENT_DOCUMENT_TYPE = 'App\Models\Adjustment';

    /**
     * Tipo de documento con el que se guardan los movimientos que genera una
     * recepción (de compra O de salida): el nombre de clase completo, NUNCA el
     * alias del morph map (mismo motivo que ADJUSTMENT_DOCUMENT_TYPE, ver
     * AdjustmentController:57-68). Se usa para distinguir, por el DOCUMENTO y
     * no por el texto de observations, las entradas que nacen de recepcionar
     * una SALIDA (remanentes, traslados) de las que nacen de una compra real.
     */
    private const RECEPTION_DOCUMENT_TYPE = 'App\Models\Reception';

    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Convert unit symbol to full name
     * Uses base_units table to get the full name
     */
    private function getUnitFullName(string $symbol): string
    {
        static $unitsCache = null;

        // Cache the units mapping
        if ($unitsCache === null) {
            $units = DB::table('base_units')
                ->where('status', 'active')
                ->get(['symbol', 'name'])
                ->keyBy('symbol')
                ->map(fn($unit) => $unit->name)
                ->toArray();

            $unitsCache = $units;
        }

        // Return full name if found, otherwise return symbol as-is
        return $unitsCache[$symbol] ?? $symbol;
    }

    /**
     * Display a listing of inventory
     * Groups by product/brand/location/batch and shows current quantities
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::query()
            ->with(['product.packagingUnits', 'brand', 'location']);

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->status;
            $query->where(function ($q) use ($status) {
                // This will be calculated in the resource, but we can filter by stored status
                $q->where('status', $status);
            });
        }

        // Search by product name or batch number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $inventory = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return InventoryResource::collection($inventory);
    }

    /**
     * Display inventory details for a specific product
     */
    public function show(string $productId): JsonResponse
    {
        $inventory = Inventory::with(['product', 'brand', 'location'])
            ->where('product_id', $productId)
            ->get();

        if ($inventory->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró inventario para este producto'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory)
        ]);
    }

    /**
     * Get inventory for a specific location
     */
    public function byLocation(string $locationId): JsonResponse
    {
        $inventory = Inventory::with(['product', 'brand', 'location'])
            ->where('location_id', $locationId)
            ->orderBy('product_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory)
        ]);
    }

    /**
     * Get inventory for a specific product across all locations
     */
    public function byProduct(string $productId): JsonResponse
    {
        $inventory = Inventory::with(['product', 'brand', 'location'])
            ->where('product_id', $productId)
            ->orderBy('location_id')
            ->get();

        // Calculate totals
        $totalQuantity = $inventory->sum('quantity');
        $totalValue = $inventory->sum('total_value');

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory),
            'summary' => [
                'totalQuantity' => $totalQuantity,
                'totalValue' => $totalValue,
                'locationsCount' => $inventory->groupBy('location_id')->count(),
            ]
        ]);
    }

    /**
     * List all inventory movements (kardex)
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $query = InventoryMovement::query()
            ->with(['product.packagingUnits', 'brand', 'location', 'responsibleUser']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Aislamiento por ubicación: los usuarios responsables de ubicación solo ven los
        // movimientos de sus ubicaciones. Solo supervisor y farm se restringen; los
        // demás roles (admin, bodega, compras, financiero, etc.) ven todo.
        $user = $request->user();
        if ($user && !$user->canViewAllLocations()) {
            $query->whereIn('location_id', $user->managedLocationIds());
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('movement_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('movement_date', '<=', $request->end_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('observations', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $movements = $query->orderBy('movement_date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return InventoryMovementResource::collection($movements);
    }

    /**
     * Get kardex (movements) for a specific product
     */
    public function movementsByProduct(Request $request, string $productId): JsonResponse
    {
        $query = InventoryMovement::query()
            ->with(['product', 'brand', 'location', 'responsibleUser'])
            ->where('product_id', $productId);

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Aislamiento por ubicación: los usuarios responsables de ubicación solo ven los
        // movimientos de sus ubicaciones. Solo supervisor y farm se restringen; los
        // demás roles (admin, bodega, compras, financiero, etc.) ven todo.
        $user = $request->user();
        if ($user && !$user->canViewAllLocations()) {
            $query->whereIn('location_id', $user->managedLocationIds());
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('movement_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('movement_date', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 15);
        $movements = $query->orderBy('movement_date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        // Calculate running balance
        $entries = InventoryMovement::where('product_id', $productId)
            ->whereIn('type', ['entry', 'adjustment'])
            ->sum('quantity');

        $exits = InventoryMovement::where('product_id', $productId)
            ->whereIn('type', ['exit', 'application'])
            ->sum('quantity');

        $balance = $entries - $exits;

        return response()->json([
            'success' => true,
            'data' => InventoryMovementResource::collection($movements),
            'summary' => [
                'totalEntries' => $entries,
                'totalExits' => $exits,
                'balance' => $balance,
            ]
        ]);
    }

    /**
     * Create a manual inventory adjustment
     * Uses pessimistic locking to prevent race conditions (ERR-004 fix)
     */
    public function adjustment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:entry,exit,adjustment'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'in:kg,litros,unidades'],
            'expiration_date' => ['nullable', 'date', 'after:today'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'movement_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
        ], [
            'type.required' => 'El tipo de movimiento es requerido',
            'movement_date.date' => 'La fecha del movimiento no es válida',
            'type.in' => 'El tipo de movimiento no es válido',
            'product_id.required' => 'El producto es requerido',
            'product_id.exists' => 'El producto seleccionado no existe',
            'brand_id.required' => 'La marca es requerida',
            'brand_id.exists' => 'La marca seleccionada no existe',
            'location_id.required' => 'La ubicación es requerida',
            'location_id.exists' => 'La ubicación seleccionada no existe',
            'quantity.required' => 'La cantidad es requerida',
            'quantity.numeric' => 'La cantidad debe ser un número',
            'quantity.min' => 'La cantidad debe ser mayor a 0',
            'unit.required' => 'La unidad es requerida',
            'unit.in' => 'La unidad seleccionada no es válida',
            'expiration_date.date' => 'La fecha de vencimiento no es válida',
            'expiration_date.after' => 'La fecha de vencimiento debe ser posterior a hoy',
            'unit_price.numeric' => 'El precio unitario debe ser un número',
            'unit_price.min' => 'El precio unitario no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['responsible_user'] = auth()->id();
            $data['total_price'] = ($data['quantity'] ?? 0) * ($data['unit_price'] ?? 0);
            $data['movement_date'] = $data['movement_date'] ?? now()->toDateString();

            // Use transaction with lock to prevent race conditions (ERR-004 fix)
            $movement = DB::transaction(function () use ($data) {
                // Lock the inventory row FIRST to prevent race conditions
                $inventory = Inventory::lockForUpdate()
                    ->where('product_id', $data['product_id'])
                    ->where('brand_id', $data['brand_id'])
                    ->where('location_id', $data['location_id'])
                    ->where('batch_number', $data['batch_number'] ?? 'MANUAL')
                    ->first();

                // If inventory doesn't exist, create it (for entries)
                if (!$inventory) {
                    if ($data['type'] === 'exit') {
                        throw new \Exception("No existe inventario para realizar la salida. Disponible: 0, Solicitado: {$data['quantity']}");
                    }

                    $inventory = Inventory::create([
                        'product_id' => $data['product_id'],
                        'brand_id' => $data['brand_id'],
                        'location_id' => $data['location_id'],
                        'batch_number' => $data['batch_number'] ?? 'MANUAL',
                        'quantity' => 0,
                        'unit' => $data['unit'],
                        'expiration_date' => $data['expiration_date'] ?? null,
                        'unit_price' => $data['unit_price'] ?? 0,
                        'total_value' => 0,
                        'status' => 'good',
                    ]);
                }

                // Calculate new quantity based on movement type
                if (in_array($data['type'], ['entry', 'adjustment'])) {
                    $newQuantity = $inventory->quantity + $data['quantity'];
                } else {
                    $newQuantity = $inventory->quantity - $data['quantity'];

                    // Prevent negative stock (ERR-006 fix)
                    if ($newQuantity < 0) {
                        throw new \Exception(
                            "Inventario insuficiente. Disponible: {$inventory->quantity}, Solicitado: {$data['quantity']}"
                        );
                    }
                }

                // Create inventory movement
                $movement = InventoryMovement::create($data);

                // Update inventory
                $inventory->quantity = $newQuantity;
                $inventory->unit_price = $data['unit_price'] ?? $inventory->unit_price;
                $inventory->total_value = $inventory->quantity * $inventory->unit_price;

                if (isset($data['expiration_date'])) {
                    $inventory->expiration_date = $data['expiration_date'];
                }

                $inventory->save();

                return $movement;
            });

            $movement->load(['product', 'brand', 'location', 'responsibleUser']);

            return response()->json([
                'success' => true,
                'message' => 'Ajuste de inventario creado exitosamente',
                'data' => new InventoryMovementResource($movement)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el ajuste de inventario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update inventory based on movement
     * @deprecated Use the inline logic in adjustment() with pessimistic locking instead
     */
    private function updateInventory(array $data): void
    {
        $inventory = Inventory::firstOrCreate(
            [
                'product_id' => $data['product_id'],
                'brand_id' => $data['brand_id'],
                'location_id' => $data['location_id'],
                'batch_number' => $data['batch_number'] ?? 'MANUAL',
            ],
            [
                'quantity' => 0,
                'unit' => $data['unit'],
                'expiration_date' => $data['expiration_date'] ?? null,
                'unit_price' => $data['unit_price'] ?? 0,
                'total_value' => 0,
                'status' => 'good',
            ]
        );

        // Update quantity based on movement type
        if (in_array($data['type'], ['entry', 'adjustment'])) {
            $inventory->quantity += $data['quantity'];
        } else {
            // Prevent negative stock (ERR-006 fix)
            $newQuantity = $inventory->quantity - $data['quantity'];
            if ($newQuantity < 0) {
                throw new \Exception(
                    "Inventario insuficiente. Disponible: {$inventory->quantity}, Solicitado: {$data['quantity']}"
                );
            }
            $inventory->quantity = $newQuantity;
        }

        // Update values
        $inventory->unit_price = $data['unit_price'] ?? $inventory->unit_price;
        $inventory->total_value = $inventory->quantity * $inventory->unit_price;

        if (isset($data['expiration_date'])) {
            $inventory->expiration_date = $data['expiration_date'];
        }

        $inventory->save();
    }

    /**
     * Get comprehensive Kardex view
     * Lists ALL products (even with 0 stock) with current inventory and ability to filter by location
     */
    public function kardex(Request $request): JsonResponse
    {
        try {
            $locationId = $request->get('location_id');
            $searchText = $request->get('search');
            $statusFilter = $request->get('status');

            // Base query to get all products
            $productsQuery = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select([
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.product_code',
                    'categories.name as category',
                    'categories.slug as category_slug',
                    'products.base_unit',
                    'products.min_stock',
                    'products.status as product_status'
                ])
                ->where('products.status', 'active');

            // Apply search filter
            if ($searchText) {
                $productsQuery->where(function($q) use ($searchText) {
                    $q->where('products.name', 'like', "%{$searchText}%")
                      ->orWhere('products.product_code', 'like', "%{$searchText}%");
                });
            }

            $products = $productsQuery->get();
            $productIds = $products->pluck('product_id')->all();

            // Single query: load all inventory rows for the product set joined with brands/locations
            $inventoryQuery = DB::table('inventory')
                ->select([
                    'inventory.id',
                    'inventory.product_id',
                    'inventory.brand_id',
                    'inventory.location_id',
                    'inventory.batch_number',
                    'inventory.quantity',
                    'inventory.unit',
                    'inventory.expiration_date',
                    'inventory.unit_price',
                    'inventory.total_value',
                    'inventory.status',
                    'brands.name as brand_name',
                    'locations.name as location_name',
                    'locations.type as location_type'
                ])
                ->join('brands', 'inventory.brand_id', '=', 'brands.id')
                ->join('locations', 'inventory.location_id', '=', 'locations.id')
                ->whereIn('inventory.product_id', $productIds ?: ['__none__']);

            if ($locationId) {
                $inventoryQuery->where('inventory.location_id', $locationId);
            }

            $allInventory = $inventoryQuery->get()->groupBy('product_id');

            // Pre-load all packaging units used in this inventory in ONE query
            $unitProductPairs = [];
            foreach ($allInventory as $items) {
                foreach ($items as $it) {
                    $unitProductPairs[$it->product_id . '|' . strtolower($it->unit)] = [
                        'product_id' => $it->product_id,
                        'unit' => $it->unit,
                    ];
                }
            }
            $packagingMap = [];
            if (!empty($unitProductPairs)) {
                $unitNames = array_unique(array_map(fn($p) => strtolower($p['unit']), array_values($unitProductPairs)));
                $rows = DB::table('packaging_units')
                    ->join('product_packaging_units', 'product_packaging_units.packaging_unit_id', '=', 'packaging_units.id')
                    ->whereIn('product_packaging_units.product_id', $productIds ?: ['__none__'])
                    ->whereIn(DB::raw('LOWER(packaging_units.name)'), $unitNames)
                    ->select('product_packaging_units.product_id', 'packaging_units.name', 'packaging_units.base_quantity')
                    ->get();
                foreach ($rows as $r) {
                    $packagingMap[$r->product_id . '|' . strtolower($r->name)] = floatval($r->base_quantity);
                }
            }
            $toBase = function ($qty, $unit, $productId) use ($packagingMap) {
                $key = $productId . '|' . strtolower($unit);
                return isset($packagingMap[$key]) ? floatval($qty) * $packagingMap[$key] : floatval($qty);
            };

            // Build inventory data for each product
            $kardexData = [];

            foreach ($products as $product) {
                $inventoryItems = $allInventory->get($product->product_id, collect());

                // Calculate totals (convert each item to base unit before summing)
                $totalQuantity = 0;
                foreach ($inventoryItems as $item) {
                    $totalQuantity += $toBase(
                        floatval($item->quantity),
                        $item->unit,
                        $product->product_id
                    );
                }
                $totalValue = $inventoryItems->sum('total_value');
                $locationsCount = $inventoryItems->unique('location_id')->count();

                // Determine overall status
                $status = 'good';
                if ($totalQuantity == 0) {
                    $status = 'out_of_stock';
                } elseif ($product->min_stock && $totalQuantity <= $product->min_stock) {
                    $status = 'low';
                }

                // Check for expiration issues
                $hasExpired = false;
                $hasNearExpiry = false;
                foreach ($inventoryItems as $item) {
                    if ($item->expiration_date) {
                        $expirationDate = new \DateTime($item->expiration_date);
                        $now = new \DateTime();
                        $interval = $now->diff($expirationDate);
                        $daysToExpiry = $interval->days * ($expirationDate < $now ? -1 : 1);

                        if ($daysToExpiry < 0) {
                            $hasExpired = true;
                        } elseif ($daysToExpiry <= 30) {
                            $hasNearExpiry = true;
                        }
                    }
                }

                if ($hasExpired) {
                    $status = 'expired';
                } elseif ($hasNearExpiry && $status !== 'low') {
                    $status = 'near_expiry';
                }

                // Apply status filter
                if ($statusFilter && $status !== $statusFilter) {
                    continue;
                }

                // Build inventory by location
                $inventoryByLocation = [];
                foreach ($inventoryItems as $item) {
                    $locationKey = $item->location_id;
                    if (!isset($inventoryByLocation[$locationKey])) {
                        $inventoryByLocation[$locationKey] = [
                            'location_id' => $item->location_id,
                            'location_name' => $item->location_name,
                            'location_type' => $item->location_type,
                            'total_quantity' => 0,
                            'total_value' => 0,
                            'batches' => []
                        ];
                    }

                    $qtyInBase = $toBase(
                        floatval($item->quantity),
                        $item->unit,
                        $product->product_id
                    );
                    $inventoryByLocation[$locationKey]['total_quantity'] += $qtyInBase;
                    $inventoryByLocation[$locationKey]['total_value'] += floatval($item->total_value);
                    $inventoryByLocation[$locationKey]['batches'][] = [
                        'id' => $item->id,
                        'brand_id' => $item->brand_id,
                        'brand_name' => $item->brand_name,
                        'batch_number' => $item->batch_number,
                        'quantity' => floatval($item->quantity),
                        'unit' => $item->unit,
                        'expiration_date' => $item->expiration_date,
                        'unit_price' => floatval($item->unit_price),
                        'total_value' => floatval($item->total_value),
                        'status' => $item->status
                    ];
                }

                $kardexData[] = [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'category' => $product->category,
                    'base_unit' => $product->base_unit,
                    'min_stock' => floatval($product->min_stock ?? 0),
                    'total_quantity' => $totalQuantity,
                    'total_value' => $totalValue,
                    'locations_count' => $locationsCount,
                    'status' => $status,
                    'inventory_by_location' => array_values($inventoryByLocation)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $kardexData,
                'summary' => [
                    'total_products' => count($kardexData),
                    'total_value' => array_sum(array_column($kardexData, 'total_value')),
                    'low_stock_count' => count(array_filter($kardexData, fn($item) => $item['status'] === 'low')),
                    'out_of_stock_count' => count(array_filter($kardexData, fn($item) => $item['status'] === 'out_of_stock')),
                    'near_expiry_count' => count(array_filter($kardexData, fn($item) => $item['status'] === 'near_expiry')),
                    'expired_count' => count(array_filter($kardexData, fn($item) => $item['status'] === 'expired'))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el kardex: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed kardex movements for a specific product
     */
    public function productKardex(Request $request, string $productId): JsonResponse
    {
        try {
            $locationId = $request->get('location_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Get product info with category
            $product = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('products.id', $productId)
                ->select([
                    'products.id',
                    'products.name',
                    'products.product_code',
                    'products.base_unit',
                    'products.min_stock',
                    'categories.name as category'
                ])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Get movements query
            $movementsQuery = DB::table('inventory_movements')
                ->select([
                    'inventory_movements.*',
                    'brands.name as brand_name',
                    'locations.name as location_name',
                    'users.name as responsible_user_name'
                ])
                ->join('brands', 'inventory_movements.brand_id', '=', 'brands.id')
                ->join('locations', 'inventory_movements.location_id', '=', 'locations.id')
                ->join('users', 'inventory_movements.responsible_user', '=', 'users.id')
                ->where('inventory_movements.product_id', $productId);

            // Apply filters
            if ($locationId) {
                $movementsQuery->where('inventory_movements.location_id', $locationId);
            }

            if ($startDate) {
                $movementsQuery->whereDate('inventory_movements.movement_date', '>=', $startDate);
            }

            if ($endDate) {
                $movementsQuery->whereDate('inventory_movements.movement_date', '<=', $endDate);
            }

            $movements = $movementsQuery
                ->orderBy('inventory_movements.movement_date', 'asc')
                ->orderBy('inventory_movements.created_at', 'asc')
                ->get();

            // Calculate running balance (ERR-001 fix: convert all quantities to base unit)
            $balance = 0;
            $kardexMovements = [];

            foreach ($movements as $movement) {
                $quantityValue = floatval($movement->quantity);

                // Convert to base unit for correct balance calculation
                $quantityInBase = $this->inventoryService->toBaseUnit($quantityValue, $movement->unit, $productId);

                if ($movement->type === 'entry') {
                    $balance += $quantityInBase;
                    $quantityIn = $quantityInBase;
                    $quantityOut = 0;
                } elseif ($movement->type === 'exit' || $movement->type === 'application') {
                    $balance -= $quantityInBase;
                    $quantityIn = 0;
                    $quantityOut = $quantityInBase;
                } elseif ($movement->type === 'adjustment') {
                    $balance += $quantityInBase;
                    $quantityIn = $quantityInBase;
                    $quantityOut = 0;
                } else {
                    $balance -= $quantityInBase;
                    $quantityIn = 0;
                    $quantityOut = $quantityInBase;
                }

                $kardexMovements[] = [
                    'id' => $movement->id,
                    'date' => $movement->movement_date,
                    'type' => $movement->type,
                    'brand_name' => $movement->brand_name,
                    'location_name' => $movement->location_name,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                    'balance' => round($balance, 4),
                    'balance_unit' => $product->base_unit,
                    'unit' => $product->base_unit,
                    'original_quantity' => $quantityValue,
                    'original_unit' => $movement->unit,
                    'unit_price' => floatval($movement->unit_price ?? 0),
                    'total_price' => floatval($movement->total_price ?? 0),
                    'responsible_user' => $movement->responsible_user_name,
                    'related_document_id' => $movement->related_document_id,
                    'related_document_type' => $movement->related_document_type,
                    'observations' => $movement->observations
                ];
            }

            // Get current inventory
            $currentInventoryQuery = DB::table('inventory')
                ->select([
                    'inventory.*',
                    'brands.name as brand_name',
                    'locations.name as location_name'
                ])
                ->join('brands', 'inventory.brand_id', '=', 'brands.id')
                ->join('locations', 'inventory.location_id', '=', 'locations.id')
                ->where('inventory.product_id', $productId);

            if ($locationId) {
                $currentInventoryQuery->where('inventory.location_id', $locationId);
            }

            $currentInventory = $currentInventoryQuery->get();

            // Calculate current stock in base unit
            $currentStockBase = 0;
            foreach ($currentInventory as $inv) {
                $currentStockBase += $this->inventoryService->toBaseUnit(
                    floatval($inv->quantity),
                    $inv->unit,
                    $productId
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_code' => $product->product_code,
                        'category' => $product->category,
                        'base_unit' => $product->base_unit,
                        'min_stock' => floatval($product->min_stock ?? 0)
                    ],
                    'movements' => $kardexMovements,
                    'current_inventory' => $currentInventory->map(function($item) use ($productId) {
                        $qtyBase = $this->inventoryService->toBaseUnit(
                            floatval($item->quantity),
                            $item->unit,
                            $productId
                        );
                        return [
                            'id' => $item->id,
                            'brand_name' => $item->brand_name,
                            'location_name' => $item->location_name,
                            'batch_number' => $item->batch_number,
                            'quantity' => floatval($item->quantity),
                            'quantity_base' => round($qtyBase, 4),
                            'unit' => $item->unit,
                            'expiration_date' => $item->expiration_date,
                            'unit_price' => floatval($item->unit_price ?? 0),
                            'total_value' => floatval($item->total_value ?? 0),
                            'status' => $item->status
                        ];
                    }),
                    'summary' => [
                        'total_movements' => count($kardexMovements),
                        // round(): evita residuos de punto flotante (p.ej. 3.55e-15)
                        // que aparecían como "Saldo Actual" en el modal de Kardex.
                        'total_entries' => round(array_sum(array_column($kardexMovements, 'quantity_in')), 4),
                        'total_exits' => round(array_sum(array_column($kardexMovements, 'quantity_out')), 4),
                        'current_balance' => round($balance, 4),
                        'current_stock' => round($currentStockBase, 4)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el kardex del producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate consolidated inventory movements report
     * Perfect for executive reports and audits with date range filtering
     */
    public function movementsReport(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $locationId = $request->get('location_id');
            $productId = $request->get('product_id');
            $type = $request->get('type');

            // Base query
            $query = DB::table('inventory_movements')
                ->select([
                    'inventory_movements.*',
                    'products.name as product_name',
                    'products.product_code',
                    'categories.name as category',
                    'categories.slug as category_slug',
                    'products.base_unit as product_base_unit',
                    'brands.name as brand_name',
                    'locations.name as location_name',
                    'locations.type as location_type',
                    'users.name as responsible_user_name'
                ])
                ->join('products', 'inventory_movements.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->join('brands', 'inventory_movements.brand_id', '=', 'brands.id')
                ->join('locations', 'inventory_movements.location_id', '=', 'locations.id')
                ->join('users', 'inventory_movements.responsible_user', '=', 'users.id');

            // Apply filters
            if ($startDate) {
                $query->whereDate('inventory_movements.movement_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('inventory_movements.movement_date', '<=', $endDate);
            }

            if ($locationId) {
                $query->where('inventory_movements.location_id', $locationId);
            }

            if ($productId) {
                $query->where('inventory_movements.product_id', $productId);
            }

            if ($type) {
                $query->where('inventory_movements.type', $type);
            }

            // Get movements ordered by date
            $movements = $query->orderBy('inventory_movements.movement_date', 'asc')->orderBy('inventory_movements.created_at', 'asc')->get();

            // Calculate statistics (INC-003 fix: include 'application' type as exits)
            $totalEntries = $movements->where('type', 'entry')->sum('quantity');
            $totalExits = $movements->whereIn('type', ['exit', 'application'])->sum('quantity');
            $totalValueEntries = $movements->where('type', 'entry')->sum('total_price');
            $totalValueExits = $movements->whereIn('type', ['exit', 'application'])->sum('total_price');

            // LOG-001 fix: calculate net_change in base units to avoid mixing different units
            $totalEntriesBase = 0;
            $totalExitsBase = 0;
            foreach ($movements as $movement) {
                $qtyBase = $this->inventoryService->toBaseUnit(
                    floatval($movement->quantity),
                    $movement->unit,
                    $movement->product_id
                );
                if ($movement->type === 'entry') {
                    $totalEntriesBase += $qtyBase;
                } elseif (in_array($movement->type, ['exit', 'application'])) {
                    $totalExitsBase += $qtyBase;
                }
            }

            // Group by product (quantities converted to base unit)
            $byProduct = [];
            foreach ($movements as $movement) {
                $productKey = $movement->product_id;
                if (!isset($byProduct[$productKey])) {
                    $byProduct[$productKey] = [
                        'product_id' => $movement->product_id,
                        'product_name' => $movement->product_name,
                        'product_code' => $movement->product_code,
                        'category' => $movement->category,
                        'total_entries' => 0,
                        'total_exits' => 0,
                        'total_value_entries' => 0,
                        'total_value_exits' => 0,
                        'movements_count' => 0,
                    ];
                }

                $byProduct[$productKey]['movements_count']++;
                $qtyBase = $this->inventoryService->toBaseUnit(
                    floatval($movement->quantity),
                    $movement->unit,
                    $movement->product_id
                );

                if ($movement->type === 'entry') {
                    $byProduct[$productKey]['total_entries'] += $qtyBase;
                    $byProduct[$productKey]['total_value_entries'] += floatval($movement->total_price ?? 0);
                } elseif (in_array($movement->type, ['exit', 'application'])) {
                    $byProduct[$productKey]['total_exits'] += $qtyBase;
                    $byProduct[$productKey]['total_value_exits'] += floatval($movement->total_price ?? 0);
                }
            }

            // Group by location (quantities converted to base unit)
            $byLocation = [];
            foreach ($movements as $movement) {
                $locationKey = $movement->location_id;
                if (!isset($byLocation[$locationKey])) {
                    $byLocation[$locationKey] = [
                        'location_id' => $movement->location_id,
                        'location_name' => $movement->location_name,
                        'location_type' => $movement->location_type,
                        'total_entries' => 0,
                        'total_exits' => 0,
                        'total_value_entries' => 0,
                        'total_value_exits' => 0,
                        'movements_count' => 0,
                    ];
                }

                $byLocation[$locationKey]['movements_count']++;
                $qtyBase = $this->inventoryService->toBaseUnit(
                    floatval($movement->quantity),
                    $movement->unit,
                    $movement->product_id
                );

                if ($movement->type === 'entry') {
                    $byLocation[$locationKey]['total_entries'] += $qtyBase;
                    $byLocation[$locationKey]['total_value_entries'] += floatval($movement->total_price ?? 0);
                } elseif (in_array($movement->type, ['exit', 'application'])) {
                    $byLocation[$locationKey]['total_exits'] += $qtyBase;
                    $byLocation[$locationKey]['total_value_exits'] += floatval($movement->total_price ?? 0);
                }
            }

            // Group by type (quantities converted to base unit)
            $byType = [
                'entry' => ['count' => 0, 'total_quantity' => 0, 'total_value' => 0],
                'exit' => ['count' => 0, 'total_quantity' => 0, 'total_value' => 0],
                'transfer' => ['count' => 0, 'total_quantity' => 0, 'total_value' => 0],
                'application' => ['count' => 0, 'total_quantity' => 0, 'total_value' => 0],
            ];

            foreach ($movements as $movement) {
                if (isset($byType[$movement->type])) {
                    $byType[$movement->type]['count']++;
                    $qtyBase = $this->inventoryService->toBaseUnit(
                        floatval($movement->quantity),
                        $movement->unit,
                        $movement->product_id
                    );
                    $byType[$movement->type]['total_quantity'] += $qtyBase;
                    $byType[$movement->type]['total_value'] += floatval($movement->total_price ?? 0);
                }
            }

            // Group by day (quantities converted to base unit)
            $byDay = [];
            foreach ($movements as $movement) {
                $date = date('Y-m-d', strtotime($movement->movement_date));
                if (!isset($byDay[$date])) {
                    $byDay[$date] = [
                        'date' => $date,
                        'total_entries' => 0,
                        'total_exits' => 0,
                        'total_value_entries' => 0,
                        'total_value_exits' => 0,
                        'movements_count' => 0,
                    ];
                }

                $byDay[$date]['movements_count']++;
                $qtyBase = $this->inventoryService->toBaseUnit(
                    floatval($movement->quantity),
                    $movement->unit,
                    $movement->product_id
                );

                if ($movement->type === 'entry') {
                    $byDay[$date]['total_entries'] += $qtyBase;
                    $byDay[$date]['total_value_entries'] += floatval($movement->total_price ?? 0);
                } elseif (in_array($movement->type, ['exit', 'application'])) {
                    $byDay[$date]['total_exits'] += $qtyBase;
                    $byDay[$date]['total_value_exits'] += floatval($movement->total_price ?? 0);
                }
            }

            // Format movements for response (include base unit quantity)
            $formattedMovements = $movements->map(function ($movement) {
                $qtyBase = $this->inventoryService->toBaseUnit(
                    floatval($movement->quantity),
                    $movement->unit,
                    $movement->product_id
                );
                return [
                    'id' => $movement->id,
                    'date' => $movement->movement_date,
                    'type' => $movement->type,
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product_name,
                    'product_code' => $movement->product_code,
                    'category' => $movement->category,
                    'brand_name' => $movement->brand_name,
                    'location_id' => $movement->location_id,
                    'location_name' => $movement->location_name,
                    'location_type' => $movement->location_type,
                    'quantity' => floatval($movement->quantity),
                    'quantity_base' => round($qtyBase, 4),
                    'unit' => $movement->unit,
                    'product_base_unit' => $movement->product_base_unit,
                    'unit_price' => floatval($movement->unit_price ?? 0),
                    'total_price' => floatval($movement->total_price ?? 0),
                    'responsible_user' => $movement->responsible_user_name,
                    'related_document_id' => $movement->related_document_id,
                    'related_document_type' => $movement->related_document_type,
                    'observations' => $movement->observations,
                ];
            });

            return response()->json([
                'success' => true,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'type' => $type,
                ],
                'movements' => $formattedMovements,
                'summary' => [
                    'total_movements' => $movements->count(),
                    'total_entries' => round($totalEntriesBase, 4),
                    'total_exits' => round($totalExitsBase, 4),
                    'total_value_entries' => $totalValueEntries,
                    'total_value_exits' => $totalValueExits,
                    'net_change' => round($totalEntriesBase - $totalExitsBase, 4),
                    'net_value_change' => $totalValueEntries - $totalValueExits,
                ],
                'by_product' => array_values($byProduct),
                'by_location' => array_values($byLocation),
                'by_type' => $byType,
                'by_day' => array_values($byDay),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte de movimientos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consumption report (outputs with type = consumption)
     * Shows products consumed, locations, and lots where they were applied
     */
    public function consumptionReport(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $locationId = $request->get('location_id'); // Destination location (farm)
            $productId = $request->get('product_id');

            // Base query - get consumption outputs
            $query = DB::table('product_outputs')
                ->select([
                    'product_outputs.id as output_id',
                    'product_outputs.output_number',
                    'product_outputs.output_date',
                    'product_outputs.observations',
                    'origin_loc.id as origin_location_id',
                    'origin_loc.name as origin_location_name',
                    'origin_loc.type as origin_location_type',
                    'dest_loc.id as destination_location_id',
                    'dest_loc.name as destination_location_name',
                    'dest_loc.type as destination_location_type',
                    'output_types.code as output_type_code',
                    'output_types.name as output_type_name',
                ])
                ->join('output_types', 'product_outputs.output_type_id', '=', 'output_types.id')
                ->join('locations as origin_loc', 'product_outputs.origin_location_id', '=', 'origin_loc.id')
                ->join('locations as dest_loc', 'product_outputs.destination_location_id', '=', 'dest_loc.id')
                ->where('output_types.code', 'consumption'); // Only consumption type

            // Apply filters
            if ($startDate) {
                $query->whereDate('product_outputs.output_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('product_outputs.output_date', '<=', $endDate);
            }

            if ($locationId) {
                $query->where('product_outputs.destination_location_id', $locationId);
            }

            $outputs = $query->orderBy('product_outputs.output_date', 'desc')->get();

            $consumptions = [];
            $totalQuantityConsumed = 0;
            $byProduct = [];
            $byLocation = [];
            $outputsPerProduct = []; // Track unique outputs per product

            foreach ($outputs as $output) {
                // Get products for this output
                $products = DB::table('output_products')
                    ->select([
                        'output_products.*',
                        'products.name as product_name',
                        'products.product_code',
                        'categories.name as category',
                        'categories.slug as category_slug',
                        'brands.name as brand_name',
                    ])
                    ->join('products', 'output_products.product_id', '=', 'products.id')
                    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->join('brands', 'output_products.brand_id', '=', 'brands.id')
                    ->where('output_products.output_id', $output->output_id);

                // Apply product filter if specified
                if ($productId) {
                    $products->where('output_products.product_id', $productId);
                }

                $products = $products->get();

                if ($products->isEmpty()) {
                    continue; // Skip if no products match the filter
                }

                // Get farm lots for this output
                $farmLots = DB::table('output_farm_lots')
                    ->select([
                        'farm_lots.id as lot_id',
                        'farm_lots.name as lot_name',
                        'farm_lots.area',
                        'farm_lots.area_unit',
                    ])
                    ->join('farm_lots', 'output_farm_lots.farm_lot_id', '=', 'farm_lots.id')
                    ->where('output_farm_lots.product_output_id', $output->output_id)
                    ->get();

                // Process each product
                foreach ($products as $product) {
                    // For CONSUMPTION reports, we need the actual quantity that LEFT the origin warehouse
                    // This is tracked in EXIT inventory movements, not reception_items
                    // Reception_items tracks what was "received" at destination, but for consumption
                    // the product is consumed/used, not stored at destination

                    // Get total EXIT quantity from inventory movements
                    $exitQuantity = DB::table('inventory_movements')
                        ->join('receptions', 'inventory_movements.related_document_id', '=', 'receptions.id')
                        ->where('inventory_movements.related_document_type', 'App\\Models\\Reception')
                        ->where('inventory_movements.type', 'exit')
                        ->where('receptions.source_type', 'output')
                        ->where('receptions.source_id', $output->output_id)
                        ->where('inventory_movements.product_id', $product->product_id)
                        ->where('inventory_movements.brand_id', $product->brand_id)
                        ->sum('inventory_movements.quantity');

                    $quantity = floatval($exitQuantity ?? 0);

                    // Get packaging unit info
                    $baseQuantity = 1;
                    $baseUnit = $product->unit;
                    $packagingUnit = DB::table('product_packaging_units')
                        ->join('packaging_units', 'product_packaging_units.packaging_unit_id', '=', 'packaging_units.id')
                        ->where('product_packaging_units.product_id', $product->product_id)
                        ->where(DB::raw('LOWER(packaging_units.name)'), strtolower($product->unit))
                        ->first(['packaging_units.base_quantity', 'packaging_units.base_unit']);

                    if ($packagingUnit) {
                        $baseQuantity = floatval($packagingUnit->base_quantity);
                        $baseUnit = $packagingUnit->base_unit;
                    }

                    $totalBaseQuantity = $quantity * $baseQuantity;

                    // Skip if no quantity has been consumed yet (no EXIT movements)
                    if ($quantity <= 0) {
                        continue;
                    }

                    $totalQuantityConsumed += $quantity;

                    // Group by product
                    $productKey = $product->product_id;
                    if (!isset($byProduct[$productKey])) {
                        $unitFullName = $this->getUnitFullName($product->unit);
                        $byProduct[$productKey] = [
                            'product_id' => $product->product_id,
                            'product_name' => $product->product_name,
                            'product_code' => $product->product_code,
                            'category' => $product->category,
                            'total_quantity' => 0,
                            'total_base_quantity' => 0,
                            'consumption_count' => 0,
                            'unit' => $product->unit,
                            'unit_full_name' => $unitFullName,
                            'base_quantity' => $baseQuantity,
                            'base_unit' => $baseUnit,
                        ];
                    }

                    $byProduct[$productKey]['total_quantity'] += $quantity;
                    $byProduct[$productKey]['total_base_quantity'] += $totalBaseQuantity;

                    // Track unique outputs per product
                    if (!isset($outputsPerProduct[$productKey])) {
                        $outputsPerProduct[$productKey] = [];
                    }
                    if (!in_array($output->output_id, $outputsPerProduct[$productKey])) {
                        $outputsPerProduct[$productKey][] = $output->output_id;
                        $byProduct[$productKey]['consumption_count']++;
                    }

                    // Group by location (destination - farm)
                    $locationKey = $output->destination_location_id;
                    if (!isset($byLocation[$locationKey])) {
                        $byLocation[$locationKey] = [
                            'location_id' => $output->destination_location_id,
                            'location_name' => $output->destination_location_name,
                            'location_type' => $output->destination_location_type,
                            'total_quantity' => 0,
                            'consumption_count' => 0,
                            'products_count' => 0,
                        ];
                    }

                    $byLocation[$locationKey]['total_quantity'] += $quantity;
                    $byLocation[$locationKey]['consumption_count']++;

                    // Add to consumptions array
                    $unitFullName = $this->getUnitFullName($product->unit);

                    $consumptions[] = [
                        'output_id' => $output->output_id,
                        'output_number' => $output->output_number,
                        'output_date' => $output->output_date,
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'category' => $product->category,
                        'brand_name' => $product->brand_name,
                        'quantity' => $quantity,
                        'unit' => $product->unit,
                        'unit_full_name' => $unitFullName,
                        'quantity_with_unit' => number_format($quantity, 2) . ' ' . $unitFullName,
                        'base_quantity' => $baseQuantity,
                        'base_unit' => $baseUnit,
                        'total_base_quantity' => $totalBaseQuantity,
                        'origin_location_id' => $output->origin_location_id,
                        'origin_location_name' => $output->origin_location_name,
                        'destination_location_id' => $output->destination_location_id,
                        'destination_location_name' => $output->destination_location_name,
                        'farm_lots' => $farmLots->map(function ($lot) {
                            return [
                                'lot_id' => $lot->lot_id,
                                'lot_name' => $lot->lot_name,
                                'area' => floatval($lot->area ?? 0),
                                'area_unit' => $lot->area_unit,
                            ];
                        })->toArray(),
                        'lots_count' => $farmLots->count(),
                        'lots_names' => $farmLots->pluck('lot_name')->implode(', '),
                        'observations' => $output->observations,
                    ];
                }
            }

            // Count unique products per location
            foreach ($byLocation as $locationKey => &$locationData) {
                $productsInLocation = array_filter($consumptions, function ($c) use ($locationKey) {
                    return $c['destination_location_id'] === $locationKey;
                });
                $uniqueProducts = array_unique(array_column($productsInLocation, 'product_id'));
                $locationData['products_count'] = count($uniqueProducts);
            }

            return response()->json([
                'success' => true,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                ],
                'consumptions' => $consumptions,
                'summary' => [
                    'total_consumptions' => count($consumptions),
                    'total_quantity_consumed' => $totalQuantityConsumed,
                    'outputs_count' => $outputs->count(),
                ],
                'by_product' => array_values($byProduct),
                'by_location' => array_values($byLocation),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte de consumo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monthly Inventory Report
     * Shows per product: initial stock, purchases, shipments to each farm, returns, final stock
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        try {
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);
            $locationId = $request->get('location_id'); // optional: filter by warehouse

            $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();
            $prevEndDate = $startDate->copy()->subSecond();

            // Get all locations (farms + warehouses)
            $locations = \App\Models\Location::where('status', 'active')
                ->orderBy('type', 'desc') // warehouses first
                ->orderBy('name')
                ->get(['id', 'name', 'type']);

            $farms = $locations->where('type', 'farm');
            $warehouses = $locations->where('type', 'warehouse');

            // Get the main warehouse (first warehouse or specified)
            $warehouseId = $locationId ?: $warehouses->first()?->id;

            // Get all products with their categories and base units
            $products = \App\Models\Product::with('category')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'product_code', 'category_id', 'base_unit']);

            $result = [];

            // Stock ACTUAL por bodega = fuente autoritativa (tabla inventory), igual que
            // "Stock Actual"/Kardex. Se precarga agrupado por producto para evitar N+1.
            $inventoryByProduct = \App\Models\Inventory::where('location_id', $warehouseId)
                ->get(['product_id', 'quantity', 'unit'])
                ->groupBy('product_id');

            // Envíos "desde la ubicación seleccionada": cada envío se registra como exit en
            // el ORIGEN + entry en el DESTINO, ligados por related_document_id. Para que la
            // matriz de fincas muestre SOLO lo que salió DESDE la ubicación elegida
            // (bodega→fincas, o finca→fincas) sin mezclar movimientos de otras ubicaciones,
            // atribuimos cada entrada en una finca al lugar de su exit emparejado.
            $originExitDocIds = InventoryMovement::where('location_id', $warehouseId)
                ->where('type', 'exit')
                ->whereNotNull('related_document_id')
                ->pluck('related_document_id')
                ->unique()
                ->values()
                ->all();

            // Recepciones de un documento de SALIDA (remanentes, traslados recepcionados,
            // etc.), separadas por output_type. Se precarga UNA SOLA VEZ, con los dos
            // joins receptions→product_outputs→output_types, para no repetirlos ~220
            // veces (una por producto) dentro del bucle de abajo.
            $outputReceptionIds = $this->outputSourcedReceptionIds();

            // Remanente devuelto a la ubicación seleccionada: se deriva del KARDEX
            // (entrada en esta ubicación cuya recepción viene de una salida tipo
            // 'remanente'), NO de product_outputs.output_date, para que cuadre contra
            // 'variation' (que se calcula sobre movimientos, no sobre la fecha del
            // documento de origen).
            $returnsByProduct = $this->sumEntriesByReceptionIds(
                $warehouseId,
                $startDate,
                $endDate,
                $outputReceptionIds['remanente']
            );

            // Envíos recibidos: entradas nacidas de recepcionar una salida que NO es
            // remanente (traslados, órdenes técnicas, solicitudes libres recepcionadas
            // en esta ubicación). Columna nueva para que, al excluirse de "Compras",
            // sigan explicadas por alguna columna del informe.
            $shipmentsInByProduct = $this->sumEntriesByReceptionIds(
                $warehouseId,
                $startDate,
                $endDate,
                $outputReceptionIds['other']
            );

            foreach ($products as $product) {
                // 1. Initial stock at start of month — SOLO la bodega del reporte
                //    (antes sumaba TODAS las ubicaciones, inflando el valor de la bodega).
                $initialStock = InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $warehouseId)
                    ->where('movement_date', '<=', $prevEndDate)
                    ->selectRaw("
                        SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END) -
                        SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END) as stock
                    ")
                    ->value('stock') ?? 0;

                // 2. Purchases during the month (entries received at the warehouse).
                //    Fix: el tipo de documento se guarda con namespace completo
                //    ('App\Models\Reception' / 'App\Models\Purchase'); antes se comparaba
                //    contra 'reception'/'purchase' y nunca casaba, por lo que las compras
                //    recibidas caían en "Variación" en vez de "Compras".
                //    Se filtra por bodega para no contar envíos a finca como compras.
                //    PR-2: además se excluyen las entradas cuya recepción viene de una
                //    SALIDA (source_type = 'output'): remanentes y traslados recibidos,
                //    que van a "Remanente"/"Envíos recibidos", no a "Compras". Se
                //    identifica por el documento (receptions.source_type), nunca por el
                //    texto de observations (que dice "Transferencia" incluso para un
                //    remanente: ver ReceptionController::createEntryMovement).
                $purchases = $this->excludingOutputSourcedReceptions(
                    InventoryMovement::where('product_id', $product->id)
                        ->where('type', 'entry')
                        ->where('location_id', $warehouseId)
                        ->whereBetween('movement_date', [$startDate, $endDate])
                        ->where(function ($q) {
                            $q->where('related_document_type', 'like', '%Reception')
                                ->orWhere('related_document_type', 'like', '%Purchase')
                                ->orWhereNull('related_document_type');
                        })
                )->sum('quantity');

                // 3. Envíos a cada finca DESDE la ubicación seleccionada (entradas en la
                //    finca emparejadas con un exit en la ubicación origen). Se excluye la
                //    propia ubicación seleccionada (no es destino de sí misma). Si la
                //    ubicación no tiene salidas, no hay envíos que atribuirle.
                $farmShipments = [];
                $totalShipped = 0;
                if (!empty($originExitDocIds)) {
                    foreach ($farms as $farm) {
                        if ($farm->id === $warehouseId) {
                            continue;
                        }
                        $shipped = InventoryMovement::where('product_id', $product->id)
                            ->where('location_id', $farm->id)
                            ->where('type', 'entry')
                            ->whereBetween('movement_date', [$startDate, $endDate])
                            ->whereIn('related_document_id', $originExitDocIds)
                            ->sum('quantity');

                        if ($shipped > 0) {
                            $farmShipments[$farm->id] = round((float) $shipped, 2);
                            $totalShipped += $shipped;
                        }
                    }
                }

                // 3b. Traslados salientes que la matriz de fincas NO explica: la
                //     matriz solo recorre ubicaciones type='farm', así que un
                //     traslado finca→bodega (el caso que el módulo de ajustes
                //     publicita: "devolver producto de mi finca a la bodega
                //     central") o bodega→bodega dejaba su SALIDA sin contabilizar
                //     en el informe del ORIGEN y aparecía como "Variación"
                //     (medido: −30 en un traslado de 30 kg).
                //     El criterio es EXCLUYENTE respecto del paso 3: se descartan
                //     los traslados cuya entrada emparejada sí quedó contada como
                //     envío a una finca, para no restarlos dos veces en el caso
                //     bodega→finca, que ya cuadraba.
                $transfersOut = $this->transferExitsNotShippedToFarms(
                    $product->id,
                    $warehouseId,
                    $startDate,
                    $endDate,
                    $originExitDocIds,
                    $this->shippedDocumentIds($product->id, $warehouseId, $farms, $startDate, $endDate, $originExitDocIds)
                );
                $totalShipped += $transfersOut;

                // 4. Remanente devuelto a la ubicación: entradas de kardex cuya
                //    recepción viene de una salida tipo 'remanente' (ver precarga
                //    $returnsByProduct arriba).
                $returns = (float) ($returnsByProduct[$product->id] ?? 0);

                // 4b. Envíos recibidos: entradas de kardex cuya recepción viene de una
                //     salida que NO es remanente (traslados, órdenes técnicas, etc.
                //     recepcionados en esta ubicación). Antes de PR-2 estas entradas
                //     caían en "Compras"; ahora quedan explicadas por esta columna.
                $shipmentsIn = (float) ($shipmentsInByProduct[$product->id] ?? 0);

                // 5. Final stock at end of month — SOLO la bodega del reporte (cierre histórico)
                $finalStock = InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $warehouseId)
                    ->where('movement_date', '<=', $endDate)
                    ->selectRaw("
                        SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END) -
                        SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END) as stock
                    ")
                    ->value('stock') ?? 0;

                // 5b. Stock ACTUAL real en la bodega (tabla inventory, convertido a unidad base).
                //     Es el valor que coincide con "Stock Actual" y con el Excel del cliente.
                $currentStock = 0;
                foreach (($inventoryByProduct[$product->id] ?? []) as $inv) {
                    $currentStock += $this->inventoryService->toBaseUnit((float) $inv->quantity, $inv->unit, $product->id);
                }

                // 6. Aumentos: ajustes que INCREMENTAN las existencias de la ubicación.
                //    Incluye la pata de ENTRADA de un traslado: para el destino es un
                //    ingreso real y ninguna otra columna la explica (no es compra), así
                //    que sin contarla quedaría como "Variación", un descuadre aparente.
                $increases = $this->whereClassifiedAsAdjustment(
                    InventoryMovement::where('product_id', $product->id)
                        ->where('type', 'entry')
                        ->where('location_id', $warehouseId)
                        ->whereBetween('movement_date', [$startDate, $endDate]),
                    ['entry', 'transfer'],
                    ['%aumento%', '%ajuste%positiv%']
                )->sum('quantity');

                // 7. Disminuciones: ajustes que REDUCEN las existencias de la ubicación.
                //    Solo los de tipo 'exit' (ajustes netos: mermas, bajas). La pata de
                //    SALIDA de un traslado queda deliberadamente fuera porque ya se
                //    resta arriba en la matriz de envíos (paso 3, emparejada por
                //    related_document_id): contarla también aquí la restaría DOS veces.
                $decreases = $this->whereClassifiedAsAdjustment(
                    InventoryMovement::where('product_id', $product->id)
                        ->whereIn('type', ['exit', 'application'])
                        ->where('location_id', $warehouseId)
                        ->whereBetween('movement_date', [$startDate, $endDate]),
                    ['exit'],
                    ['%disminuc%', '%ajuste%negativ%']
                )->sum('quantity');

                // Total movements = purchases + shipments_in + increases - shipped - decreases + returns
                $totalMov = round((float)$purchases + (float)$shipmentsIn + (float)$increases - (float)$totalShipped - (float)$decreases + (float)$returns, 2);
                // Variation = final - initial - totalMov (should be 0 if everything balances)
                $variation = round((float)$finalStock - (float)$initialStock - $totalMov, 2);

                // Only include products that have any activity or stock
                if ($initialStock != 0 || $purchases > 0 || $totalShipped > 0 || $returns > 0 || $shipmentsIn > 0 || $finalStock != 0 || $increases > 0 || $decreases > 0 || $currentStock != 0) {
                    $result[] = [
                        'product_id' => $product->id,
                        'product_code' => $product->product_code,
                        'product_name' => $product->name,
                        'category' => $product->category?->name ?? 'Sin categoría',
                        'unit' => $product->base_unit,
                        'initial_stock' => round((float) $initialStock, 2),
                        'purchases' => round((float) $purchases, 2),
                        'farm_shipments' => $farmShipments,
                        'total_shipped' => round((float) $totalShipped, 2),
                        // Parte de total_shipped que NO fue a una finca (traslados
                        // a bodegas u otras ubicaciones), separada para poder
                        // auditar la diferencia contra la matriz de fincas.
                        'transfers_out' => round((float) $transfersOut, 2),
                        'returns' => round((float) $returns, 2),
                        // Envíos recibidos (traslados/órdenes recepcionados que NO son
                        // remanente): antes de PR-2 caían en "Compras".
                        'shipments_in' => round((float) $shipmentsIn, 2),
                        'increases' => round((float) $increases, 2),
                        'decreases' => round((float) $decreases, 2),
                        'total_movements' => $totalMov,
                        'variation' => $variation,
                        'final_stock' => round((float) $finalStock, 2),
                        // Stock actual real en bodega (coincide con Stock Actual / Excel)
                        'current_stock' => round((float) $currentStock, 2),
                    ];
                }
            }

            // Build farm columns list
            $farmColumns = $farms->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
            ])->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'month' => $month,
                    'year' => $year,
                    'warehouse' => $locations->firstWhere('id', $warehouseId)?->name ?? 'N/A',
                    'farm_columns' => $farmColumns,
                    'products' => $result,
                    'summary' => [
                        'total_products' => count($result),
                        'total_with_stock' => count(array_filter($result, fn($p) => $p['final_stock'] > 0)),
                        'total_purchases' => round(array_sum(array_column($result, 'purchases')), 2),
                        // Incluye envíos a fincas Y transfersOut (traslados a
                        // ubicaciones que no son finca, p. ej. bodega -> bodega).
                        // El frontend lo etiqueta "Total Enviado / Trasladado"
                        // (no "a Fincas") para que el rótulo sea veraz.
                        'total_shipped' => round(array_sum(array_column($result, 'total_shipped')), 2),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte mensual: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Documentos cuya ENTRADA ya quedó contada como envío en la matriz de fincas
     * del inventario mensual (paso 3), para poder excluirlos del conteo de
     * traslados salientes y no restar dos veces la misma salida.
     *
     * Replica exactamente los filtros del paso 3 (fincas activas, excluida la
     * propia ubicación del informe, mismo producto, mismo rango, emparejadas por
     * related_document_id con un exit del origen).
     *
     * @param  \Illuminate\Support\Collection  $farms
     * @param  array<int, string>  $originExitDocIds
     * @return array<int, string>
     */
    private function shippedDocumentIds(
        string $productId,
        string $warehouseId,
        $farms,
        $startDate,
        $endDate,
        array $originExitDocIds
    ): array {
        if (empty($originExitDocIds)) {
            return [];
        }

        $farmIds = $farms->pluck('id')->reject(fn ($id) => $id === $warehouseId)->values()->all();

        if (empty($farmIds)) {
            return [];
        }

        return InventoryMovement::where('product_id', $productId)
            ->whereIn('location_id', $farmIds)
            ->where('type', 'entry')
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->whereIn('related_document_id', $originExitDocIds)
            ->distinct()
            ->pluck('related_document_id')
            ->all();
    }

    /**
     * Suma de las SALIDAS por traslado desde la ubicación del informe cuya
     * entrada emparejada NO se contabilizó como envío a una finca.
     *
     * Es la pata que faltaba para que la "Variación" del origen sea 0 en un
     * traslado finca→bodega o bodega→bodega: la salida existe en el kardex y baja
     * el stock, pero ninguna columna del informe la explicaba.
     *
     * Solo mira movimientos que provienen de una solicitud de ajuste de tipo
     * 'transfer' (el documento manda, no el texto de las observaciones): las
     * salidas de ajustes NETOS ya se cuentan en "Disminuciones" y las de
     * recepciones/aplicaciones tienen otro documento y otras columnas.
     *
     * @param  array<int, string>  $originExitDocIds
     * @param  array<int, string>  $shippedDocIds
     */
    private function transferExitsNotShippedToFarms(
        string $productId,
        string $warehouseId,
        $startDate,
        $endDate,
        array $originExitDocIds,
        array $shippedDocIds
    ): float {
        if (empty($originExitDocIds)) {
            return 0.0;
        }

        $query = InventoryMovement::where('product_id', $productId)
            ->where('location_id', $warehouseId)
            ->where('type', 'exit')
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->where('related_document_type', self::ADJUSTMENT_DOCUMENT_TYPE)
            ->whereExists(function ($exists) {
                $exists->selectRaw('1')
                    ->from('adjustments')
                    ->whereColumn('adjustments.id', 'inventory_movements.related_document_id')
                    ->where('adjustments.type', 'transfer');
            });

        if (!empty($shippedDocIds)) {
            $query->whereNotIn('related_document_id', $shippedDocIds);
        }

        return (float) $query->sum('quantity');
    }

    /**
     * Restringe una consulta de movimientos a los que cuentan en las columnas
     * "Aumentos" / "Disminuciones" del inventario mensual.
     *
     * La clasificación depende del ORIGEN del movimiento:
     *
     * - Si viene de una solicitud de ajuste (related_document_type =
     *   'App\Models\Adjustment'), se clasifica por el TIPO DEL DOCUMENTO
     *   (adjustments.type), nunca por el texto. Las observaciones incluyen las
     *   notas que escribe el solicitante, así que clasificar por texto deja el
     *   informe a merced de lo que alguien redacte: una nota con la palabra
     *   "disminución" convertiría la SALIDA de un traslado en una disminución,
     *   y esa salida ya se resta en la matriz de envíos (ambas patas del
     *   traslado comparten related_document_id) — se restaría dos veces y la
     *   "Variación" del origen dejaría de ser 0, que es justo la columna que el
     *   cliente concilia contra contabilidad.
     *
     * - Si NO viene de un ajuste (incluidos los movimientos antiguos, donde
     *   related_document_type es NULL), se clasifica por el texto de la
     *   observación: es la única marca que tiene el histórico previo al módulo
     *   de ajustes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  consulta ya filtrada por producto, tipo de movimiento, ubicación y rango de fechas
     * @param  array<int, string>  $adjustmentTypes  valores de adjustments.type que cuentan en esta columna
     * @param  array<int, string>  $legacyObservationPatterns  patrones LIKE para los movimientos que no provienen de un ajuste
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function whereClassifiedAsAdjustment($query, array $adjustmentTypes, array $legacyObservationPatterns)
    {
        return $query->where(function ($outer) use ($adjustmentTypes, $legacyObservationPatterns) {
            // Proviene de una solicitud de ajuste: manda el tipo del documento.
            $outer->where(function ($fromAdjustment) use ($adjustmentTypes) {
                $fromAdjustment
                    ->where('related_document_type', self::ADJUSTMENT_DOCUMENT_TYPE)
                    ->whereExists(function ($exists) use ($adjustmentTypes) {
                        $exists->selectRaw('1')
                            ->from('adjustments')
                            ->whereColumn('adjustments.id', 'inventory_movements.related_document_id')
                            ->whereIn('adjustments.type', $adjustmentTypes);
                    });
            });

            // Histórico / cualquier otro documento: manda el texto.
            $outer->orWhere(function ($legacy) use ($legacyObservationPatterns) {
                $legacy->where(function ($notFromAdjustment) {
                    $notFromAdjustment
                        ->where('related_document_type', '!=', self::ADJUSTMENT_DOCUMENT_TYPE)
                        ->orWhereNull('related_document_type');
                })->where(function ($byText) use ($legacyObservationPatterns) {
                    // Guarda obligatoria: sin patrones, este where anidado se
                    // compilaría vacío y el predicado degeneraría en una
                    // tautología ("no viene de un ajuste") que sumaría TODOS los
                    // movimientos del rango en la columna de aumentos o de
                    // disminuciones. Sin patrones no hay nada que clasificar por
                    // texto, así que la respuesta correcta es "ninguno".
                    if (empty($legacyObservationPatterns)) {
                        $byText->whereRaw('0 = 1');

                        return;
                    }

                    foreach ($legacyObservationPatterns as $pattern) {
                        $byText->orWhere('observations', 'like', $pattern);
                    }
                });
            });
        });
    }

    /**
     * REPORTE 1 (cierre de mes): Inventario mensual POR FINCA seleccionada.
     * Por producto: inv. inicial (finca) + entradas (envíos a la finca) − consumo
     * − remanente devuelto a bodega = inv. final (finca).
     * Entradas/inicial salen de inventory_movements (type='entry' en la finca).
     * Remanente y consumo salen de los documentos de salida (output_type 'remanente'
     * y 'consumption') con origen = la finca. Hoy están en 0 y se llenarán cuando se
     * registren esas devoluciones/consumos; las columnas ya quedan listas.
     */
    public function farmMonthlyReport(Request $request): JsonResponse
    {
        try {
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);
            $fincaId = $request->get('location_id');

            if (!$fincaId) {
                return response()->json(['success' => false, 'message' => 'Debe seleccionar una finca (location_id).'], 422);
            }
            $finca = \App\Models\Location::find($fincaId);
            if (!$finca) {
                return response()->json(['success' => false, 'message' => 'Finca no encontrada.'], 404);
            }

            $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();
            $prevEnd = $startDate->copy()->subSecond();

            // Remanente a bodega y consumo: desde documentos de salida por tipo, origen = finca
            $remanenteByProduct = $this->farmOutputQtyByProduct($fincaId, 'remanente', $startDate, $endDate);
            $consumoByProduct = $this->farmOutputQtyByProduct($fincaId, 'consumption', $startDate, $endDate);

            $products = \App\Models\Product::with('category')
                ->where('status', 'active')->orderBy('name')
                ->get(['id', 'name', 'product_code', 'category_id', 'base_unit']);

            $result = [];
            foreach ($products as $product) {
                $initial = (float) (InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $fincaId)
                    ->where('movement_date', '<=', $prevEnd)
                    ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','application') THEN quantity ELSE 0 END) as s")
                    ->value('s') ?? 0);

                $entries = (float) InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $fincaId)
                    ->where('type', 'entry')
                    ->whereBetween('movement_date', [$startDate, $endDate])
                    ->sum('quantity');

                $remanente = (float) ($remanenteByProduct[$product->id] ?? 0);
                $consumo = (float) ($consumoByProduct[$product->id] ?? 0);

                // Salidas de la finca que NO pasan por un documento de salida
                // (ajustes de salida, traslados salientes, aplicaciones sueltas,
                // histórico sin documento). Sin restarlas, el informe reportaba
                // stock que la finca ya no tiene (medido: final_stock 100 con 70
                // reales tras un ajuste de salida de 30) y rompía la continuidad
                // final(mes N) == inicial(mes N+1), porque el inventario inicial
                // SÍ resta todas las salidas.
                $otherExits = $this->farmExitsOutsideOutputs($product->id, $fincaId, $startDate, $endDate);

                $final = round($initial + $entries - $consumo - $remanente - $otherExits, 2);

                if ($initial != 0 || $entries > 0 || $remanente > 0 || $consumo > 0 || $otherExits > 0) {
                    $result[] = [
                        'product_id' => $product->id,
                        'product_code' => $product->product_code,
                        'product_name' => $product->name,
                        'category' => $product->category?->name ?? 'Sin categoría',
                        'unit' => $product->base_unit,
                        'initial_stock' => round($initial, 2),
                        'entries' => round($entries, 2),
                        'consumption' => round($consumo, 2),
                        'remanente_to_warehouse' => round($remanente, 2),
                        'other_exits' => round($otherExits, 2),
                        'final_stock' => $final,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'month' => $month,
                    'year' => $year,
                    'farm' => $finca->name,
                    'farm_id' => $finca->id,
                    'products' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar el inventario por finca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Salidas de kardex de una finca que NINGUNA otra columna del inventario por
     * finca explica.
     *
     * Las columnas "consumo" y "remanente" se derivan de los documentos de salida
     * (product_outputs), cuyos movimientos de kardex se registran al recepcionar
     * la salida con related_document_type = 'App\Models\Reception' (ver
     * ReceptionController). Esas se excluyen aquí para no restarlas dos veces.
     *
     * Todo lo demás (ajustes de salida, la pata de salida de un traslado,
     * aplicaciones registradas por su propio módulo, movimientos históricos sin
     * documento) reduce el stock real de la finca y debe restarse: es exactamente
     * lo que hace el cálculo del inventario INICIAL, que resta todas las salidas
     * hasta la fecha de corte.
     */
    private function farmExitsOutsideOutputs(string $productId, string $fincaId, $start, $end): float
    {
        return (float) InventoryMovement::where('product_id', $productId)
            ->where('location_id', $fincaId)
            ->where('type', 'exit')
            ->whereBetween('movement_date', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('related_document_type')
                    ->orWhere('related_document_type', 'not like', '%Reception');
            })
            ->sum('quantity');
    }

    /**
     * Suma de cantidad entregada por producto, desde salidas de un tipo dado
     * (output_type code) con origen en la finca, en el rango de fechas.
     */
    private function farmOutputQtyByProduct(string $fincaId, string $typeCode, $start, $end): array
    {
        return \App\Models\OutputProduct::query()
            ->selectRaw('output_products.product_id, SUM(output_products.quantity_delivered) as q')
            ->join('product_outputs', 'product_outputs.id', '=', 'output_products.output_id')
            ->join('output_types', 'output_types.id', '=', 'product_outputs.output_type_id')
            ->where('output_types.code', $typeCode)
            ->where('product_outputs.origin_location_id', $fincaId)
            ->where('product_outputs.status', '!=', 'cancelled')
            ->whereBetween('product_outputs.output_date', [$start, $end])
            ->groupBy('output_products.product_id')
            ->pluck('q', 'output_products.product_id')
            ->toArray();
    }

    /**
     * Excluye de la consulta las entradas cuya recepción (related_document_id)
     * proviene de un documento de SALIDA (receptions.source_type = 'output'):
     * remanentes, traslados recepcionados, órdenes técnicas, etc.
     *
     * Se identifica por el DOCUMENTO, nunca por el texto de `observations`: la
     * entrada de un remanente dice literalmente "... - Transferencia" (ver
     * ReceptionController::createEntryMovement), así que clasificar por texto
     * lo confundiría con un traslado real.
     *
     * Un único join contra `receptions` (por su clave primaria): a diferencia
     * de {@see outputSourcedReceptionIds()}, esta consulta NO necesita saber el
     * output_type (cualquier salida recepcionada se excluye de "Compras"), así
     * que no arrastra los joins a product_outputs/output_types dentro del
     * bucle de productos del informe mensual.
     */
    private function excludingOutputSourcedReceptions($query)
    {
        return $query->whereNotExists(function ($exists) {
            $exists->selectRaw('1')
                ->from('receptions')
                ->whereColumn('receptions.id', 'inventory_movements.related_document_id')
                ->where('receptions.source_type', 'output');
        });
    }

    /**
     * IDs de receptions.id (= inventory_movements.related_document_id) que
     * nacen de recepcionar un documento de SALIDA, separados según si el
     * output_type es 'remanente' o cualquier otro (traslado, orden técnica,
     * solicitud libre).
     *
     * Requiere 2 joins (receptions → product_outputs → output_types), así que
     * se calcula UNA SOLA VEZ por llamada al informe mensual (ver uso en
     * monthlyReport) en vez de repetirlos dentro del bucle de ~220 productos,
     * que es lo que degradaba un barrido de 20 ubicaciones × 3 meses a más de
     * 300 s.
     *
     * @return array{remanente: array<int, string>, other: array<int, string>}
     */
    private function outputSourcedReceptionIds(): array
    {
        $rows = DB::table('receptions')
            ->join('product_outputs', 'product_outputs.id', '=', 'receptions.source_id')
            ->join('output_types', 'output_types.id', '=', 'product_outputs.output_type_id')
            ->where('receptions.source_type', 'output')
            ->select('receptions.id', 'output_types.code')
            ->get();

        $remanente = [];
        $other = [];

        foreach ($rows as $row) {
            if ($row->code === 'remanente') {
                $remanente[] = $row->id;
            } else {
                $other[] = $row->id;
            }
        }

        return ['remanente' => $remanente, 'other' => $other];
    }

    /**
     * Suma, por producto, las entradas de kardex en la ubicación y rango dados
     * cuyo related_document_id está en la lista de recepciones dada.
     *
     * Se usa para las columnas "Remanente" y "Envíos recibidos" del inventario
     * mensual, derivadas del KARDEX (movement_date) y no de
     * product_outputs.output_date: es lo que hace que cuadren contra
     * 'variation', que se calcula sobre movimientos.
     *
     * @param  array<int, string>  $receptionIds
     * @return array<string, float>
     */
    private function sumEntriesByReceptionIds(string $locationId, $start, $end, array $receptionIds): array
    {
        if (empty($receptionIds)) {
            return [];
        }

        return InventoryMovement::where('location_id', $locationId)
            ->where('type', 'entry')
            ->whereBetween('movement_date', [$start, $end])
            ->whereIn('related_document_id', $receptionIds)
            ->selectRaw('product_id, SUM(quantity) as q')
            ->groupBy('product_id')
            ->pluck('q', 'product_id')
            ->toArray();
    }

    /**
     * REPORTE 2 (cierre de mes): Informe consolidado de ENTRADAS a una finca X.
     * Lista por producto las entradas (envíos recibidos) a la finca en el rango.
     */
    public function farmEntriesReport(Request $request): JsonResponse
    {
        try {
            $fincaId = $request->get('location_id');
            if (!$fincaId) {
                return response()->json(['success' => false, 'message' => 'Debe seleccionar una finca (location_id).'], 422);
            }
            $finca = \App\Models\Location::find($fincaId);
            if (!$finca) {
                return response()->json(['success' => false, 'message' => 'Finca no encontrada.'], 404);
            }

            $query = InventoryMovement::query()
                ->selectRaw('inventory_movements.product_id, products.product_code, products.name as product_name, inventory_movements.unit, brands.name as brand_name, SUM(inventory_movements.quantity) as total_quantity, COUNT(*) as movements')
                ->join('products', 'products.id', '=', 'inventory_movements.product_id')
                ->leftJoin('brands', 'brands.id', '=', 'inventory_movements.brand_id')
                ->where('inventory_movements.type', 'entry')
                ->where('inventory_movements.location_id', $fincaId);

            if ($request->filled('date_from')) {
                $query->where('inventory_movements.movement_date', '>=', $request->date_from . ' 00:00:00');
            }
            if ($request->filled('date_to')) {
                $query->where('inventory_movements.movement_date', '<=', $request->date_to . ' 23:59:59');
            }

            $rows = $query->groupBy('inventory_movements.product_id', 'products.product_code', 'products.name', 'inventory_movements.unit', 'brands.name')
                ->orderByDesc('total_quantity')
                ->get()
                ->map(fn ($r) => [
                    'product_id' => $r->product_id,
                    'product_code' => $r->product_code,
                    'product_name' => $r->product_name,
                    'brand_name' => $r->brand_name ?? 'Sin Marca',
                    'unit' => $r->unit,
                    'total_quantity' => round((float) $r->total_quantity, 2),
                    'movements' => (int) $r->movements,
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'farm' => $finca->name,
                    'farm_id' => $finca->id,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'products' => $rows,
                    'summary' => [
                        'total_products' => $rows->count(),
                        'total_movements' => $rows->sum('movements'),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar entradas por finca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Product Listing Report by Date
     * Shows stock at a given date, current stock, and the differential
     */
    public function productListingReport(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', now()->format('Y-m-d'));
            $locationId = $request->get('location_id');
            $targetDate = \Carbon\Carbon::parse($date)->endOfDay();

            // Get products with their categories
            $products = \App\Models\Product::with('category')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'product_code', 'category_id', 'base_unit', 'status']);

            // Get locations for grouping
            $locations = \App\Models\Location::where('status', 'active')->get(['id', 'name', 'type']);

            // If location specified, only that one; otherwise group by all locations with stock
            $targetLocations = $locationId
                ? $locations->where('id', $locationId)
                : $locations;

            $result = [];

            foreach ($targetLocations as $location) {
                foreach ($products as $product) {
                    // Stock at the given date
                    $stockAtDate = InventoryMovement::where('product_id', $product->id)
                        ->where('location_id', $location->id)
                        ->where('movement_date', '<=', $targetDate)
                        ->selectRaw("
                            COALESCE(SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END), 0) -
                            COALESCE(SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END), 0) as stock
                        ")
                        ->value('stock') ?? 0;

                    // Current stock (all time)
                    $currentStock = InventoryMovement::where('product_id', $product->id)
                        ->where('location_id', $location->id)
                        ->selectRaw("
                            COALESCE(SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END), 0) -
                            COALESCE(SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END), 0) as stock
                        ")
                        ->value('stock') ?? 0;

                    $differential = round((float)$currentStock - (float)$stockAtDate, 2);

                    if ($stockAtDate != 0 || $currentStock != 0) {
                        $result[] = [
                            'location' => $location->name,
                            'location_type' => $location->type,
                            'product_code' => $product->product_code,
                            'category' => $product->category?->name ?? 'Sin categoría',
                            'product_name' => $product->name,
                            'unit' => $product->base_unit,
                            'stock_at_date' => round((float)$stockAtDate, 2),
                            'differential' => $differential,
                            'current_stock' => round((float)$currentStock, 2),
                            'status' => $product->status === 'active' ? 'ACTIVO' : 'INACTIVO',
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'location' => $locationId ? $locations->firstWhere('id', $locationId)?->name : 'Todas',
                    'products' => $result,
                    'summary' => [
                        'total' => count($result),
                        'with_stock' => count(array_filter($result, fn($p) => $p['current_stock'] > 0)),
                        'with_differential' => count(array_filter($result, fn($p) => $p['differential'] != 0)),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el listado: ' . $e->getMessage()
            ], 500);
        }
    }
}
