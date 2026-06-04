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

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
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
        $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

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

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 15);
        $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
            'observations' => ['nullable', 'string'],
        ], [
            'type.required' => 'El tipo de movimiento es requerido',
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
                $movementsQuery->whereDate('inventory_movements.created_at', '>=', $startDate);
            }

            if ($endDate) {
                $movementsQuery->whereDate('inventory_movements.created_at', '<=', $endDate);
            }

            $movements = $movementsQuery
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
                    'date' => $movement->created_at,
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
                $query->whereDate('inventory_movements.created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('inventory_movements.created_at', '<=', $endDate);
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
            $movements = $query->orderBy('inventory_movements.created_at', 'asc')->get();

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
                $date = date('Y-m-d', strtotime($movement->created_at));
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
                    'date' => $movement->created_at,
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

            foreach ($products as $product) {
                // 1. Initial stock at start of month — SOLO la bodega del reporte
                //    (antes sumaba TODAS las ubicaciones, inflando el valor de la bodega).
                $initialStock = InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $warehouseId)
                    ->where('created_at', '<=', $prevEndDate)
                    ->selectRaw("
                        SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END) -
                        SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END) as stock
                    ")
                    ->value('stock') ?? 0;

                // 2. Purchases during the month (entries from purchases)
                $purchases = InventoryMovement::where('product_id', $product->id)
                    ->where('type', 'entry')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where(function ($q) {
                        $q->where('related_document_type', 'purchase')
                            ->orWhere('related_document_type', 'reception')
                            ->orWhereNull('related_document_type');
                    })
                    ->sum('quantity');

                // 3. Shipments to each farm (exits/transfers during the month)
                $farmShipments = [];
                $totalShipped = 0;
                foreach ($farms as $farm) {
                    // Entries at the farm = shipments TO the farm
                    $shipped = InventoryMovement::where('product_id', $product->id)
                        ->where('location_id', $farm->id)
                        ->where('type', 'entry')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->sum('quantity');

                    if ($shipped > 0) {
                        $farmShipments[$farm->id] = round((float) $shipped, 2);
                        $totalShipped += $shipped;
                    }
                }

                // 4. Returns/Remanentes (exits from farms back to warehouse)
                $returns = InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $warehouseId)
                    ->where('type', 'entry')
                    ->where('observations', 'like', '%remanente%')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('quantity');

                // 5. Final stock at end of month — SOLO la bodega del reporte (cierre histórico)
                $finalStock = InventoryMovement::where('product_id', $product->id)
                    ->where('location_id', $warehouseId)
                    ->where('created_at', '<=', $endDate)
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

                // 6. Increases (adjustments positive - not purchases, not remanentes)
                $increases = InventoryMovement::where('product_id', $product->id)
                    ->where('type', 'entry')
                    ->where('location_id', $warehouseId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where(function ($q) {
                        $q->where('observations', 'like', '%aumento%')
                            ->orWhere('observations', 'like', '%ajuste%positiv%');
                    })
                    ->sum('quantity');

                // 7. Decreases (adjustments negative - not shipments)
                $decreases = InventoryMovement::where('product_id', $product->id)
                    ->whereIn('type', ['exit', 'application'])
                    ->where('location_id', $warehouseId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where(function ($q) {
                        $q->where('observations', 'like', '%disminuc%')
                            ->orWhere('observations', 'like', '%ajuste%negativ%');
                    })
                    ->sum('quantity');

                // Total movements = purchases + increases - shipped - decreases + returns
                $totalMov = round((float)$purchases + (float)$increases - (float)$totalShipped - (float)$decreases + (float)$returns, 2);
                // Variation = final - initial - totalMov (should be 0 if everything balances)
                $variation = round((float)$finalStock - (float)$initialStock - $totalMov, 2);

                // Only include products that have any activity or stock
                if ($initialStock != 0 || $purchases > 0 || $totalShipped > 0 || $returns > 0 || $finalStock != 0 || $increases > 0 || $decreases > 0 || $currentStock != 0) {
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
                        'returns' => round((float) $returns, 2),
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
                    'warehouse' => $warehouses->firstWhere('id', $warehouseId)?->name ?? 'N/A',
                    'farm_columns' => $farmColumns,
                    'products' => $result,
                    'summary' => [
                        'total_products' => count($result),
                        'total_with_stock' => count(array_filter($result, fn($p) => $p['final_stock'] > 0)),
                        'total_purchases' => round(array_sum(array_column($result, 'purchases')), 2),
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
                        ->where('created_at', '<=', $targetDate)
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
