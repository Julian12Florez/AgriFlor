<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Inventory;
use App\Services\CommittedStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private CommittedStockService $committedStockService)
    {
    }

    /**
     * Display a listing of products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(['brand', 'category', 'packagingUnits']);

        // Filter by category_id
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by brand_id
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Search by name or active_ingredient
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('active_ingredient', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';
        $data['created_by'] = auth()->id();

        // Extract packaging_unit_ids if present
        $packagingUnitIds = $data['packaging_unit_ids'] ?? [];
        unset($data['packaging_unit_ids']);

        $product = Product::create($data);

        // Sync packaging units if provided
        if (!empty($packagingUnitIds)) {
            $product->packagingUnits()->sync($packagingUnitIds);
        }

        $product->load(['brand', 'packagingUnits']);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'data' => new ProductResource($product)
        ], 201);
    }

    /**
     * Display the specified product
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('brand')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validated();

        // Extract packaging_unit_ids if present
        $packagingUnitIds = $data['packaging_unit_ids'] ?? null;
        unset($data['packaging_unit_ids']);

        $product->update($data);

        // Sync packaging units if provided
        if ($packagingUnitIds !== null) {
            $product->packagingUnits()->sync($packagingUnitIds);
        }

        $product->load(['brand', 'packagingUnits']);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente',
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Remove the specified product
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ]);
    }

    /**
     * Search products with real-time inventory information by location
     */
    public function searchWithInventory(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|uuid|exists:locations,id',
            'search' => 'nullable|string',
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $query = Product::query()->with('category')->where('status', 'active');

        // Filter by search term
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('active_ingredient', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get()->map(function ($product) use ($request) {
            // Get inventory grouped by brand for this product and location
            $inventoryByBrand = Inventory::where('product_id', $product->id)
                ->where('location_id', $request->location_id)
                ->whereNotIn('status', ['expired'])
                ->with('brand')
                ->get()
                ->groupBy('brand_id');

            $brands = [];

            foreach ($inventoryByBrand as $brandId => $inventories) {
                $brand = $inventories->first()->brand;

                $brands[] = [
                    'brand_id' => $brandId,
                    'brand_name' => $brand ? $brand->name : 'Sin marca',
                    'available_quantity' => $inventories->sum('quantity'),
                    'unit' => $inventories->first()->unit,
                    'batches' => $inventories->sortBy('expiration_date')->map(function ($inv) {
                        return [
                            'inventory_id' => $inv->id,
                            'quantity' => $inv->quantity,
                            'expiration_date' => $inv->expiration_date,
                            'created_at' => $inv->created_at->format('Y-m-d H:i:s'),
                            'days_to_expiry' => $inv->expiration_date
                                ? now()->diffInDays($inv->expiration_date, false)
                                : null,
                        ];
                    })->values()->toArray(),
                ];
            }

            // Only return products with available inventory
            if (count($brands) === 0) {
                return null;
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'category_id' => $product->category_id,
                'active_ingredient' => $product->active_ingredient,
                'description' => $product->description,
                'min_stock' => $product->min_stock,
                'max_stock' => $product->max_stock,
                'packaging_units' => $product->packagingUnits->map(fn($pu) => [
                    'id' => $pu->id,
                    'name' => $pu->name,
                    'base_quantity' => $pu->base_quantity,
                    'base_unit' => $pu->base_unit,
                ]),
                'brands' => $brands,
                'total_available' => collect($brands)->sum('available_quantity'),
            ];
        })->filter()->values(); // Remove null entries (products without inventory)

        return response()->json([
            'success' => true,
            'message' => 'Productos con inventario obtenidos exitosamente',
            'data' => $products,
            'count' => $products->count(),
        ]);
    }

    /**
     * Get products with inventory details for product outputs
     * Returns products ordered by expiration date (FIFO — vencimientos sin
     * fecha van al final, no al frente) and then by stock
     */
    public function getForOutputs(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|uuid|exists:locations,id',
            'search' => 'nullable|string',
        ]);

        // Get all inventory items for the location (including expired — users may need to dispose of them)
        // FIFO real: MySQL ordena los NULL primero por defecto, así que "Sin
        // vencimiento" encabezaba la lista por delante de lotes que sí vencen —
        // rompía el criterio FIFO que el propio rótulo del selector promete
        // ("producto ordenado por vencimiento"). orderByRaw manda los NULL al
        // final (C-3, PR-C).
        $inventoryItems = Inventory::where('location_id', $request->location_id)
            ->where('quantity', '>', 0)
            ->with(['product.packagingUnits', 'brand'])
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('quantity', 'desc') // Then by stock
            ->get();

        // Comprometido/disponible por [producto, marca]: se calcula sobre TODO el
        // inventario de la ubicación (antes del filtro de búsqueda, que solo decide
        // qué filas se muestran, no cuánto hay disponible). Usa la MISMA regla que
        // ProductOutputController::store() (vía CommittedStockService), para que el
        // desplegable jamás ofrezca algo que el backend luego rechace con 422
        // "Stock insuficiente" (C-3, PR-C).
        $otherOutputs = $this->committedStockService->otherOutputsForLocation($request->location_id);

        $groupPhysicalBase = [];
        foreach ($inventoryItems as $item) {
            [$baseQty] = $this->resolveBaseQuantity($item);
            $key = $item->product_id . '|' . $item->brand_id;
            $groupPhysicalBase[$key] = ($groupPhysicalBase[$key] ?? 0) + $baseQty;
        }

        $groupCommitted = [];
        $groupAvailable = [];
        foreach ($groupPhysicalBase as $key => $physical) {
            [$productId, $brandId] = explode('|', $key, 2);
            $committed = $this->committedStockService
                ->committedBreakdown($otherOutputs, $productId, $brandId)['total'];
            $groupCommitted[$key] = $committed;
            $groupAvailable[$key] = max(0, $physical - $committed);
        }

        // Apply search filter if provided
        if ($request->search) {
            $search = strtolower($request->search);
            $inventoryItems = $inventoryItems->filter(function ($item) use ($search) {
                return str_contains(strtolower($item->product->name), $search) ||
                       str_contains(strtolower($item->product->product_code ?? ''), $search) ||
                       str_contains(strtolower($item->product->active_ingredient ?? ''), $search) ||
                       str_contains(strtolower($item->brand->name ?? ''), $search);
            });
        }

        // Format data for frontend dropdown
        $formattedData = $inventoryItems->map(function ($item) use ($groupCommitted, $groupAvailable) {
            $expirationDate = $item->expiration_date
                ? $item->expiration_date->format('d/m/Y')
                : 'Sin vencimiento';

            $daysToExpiry = null;
            if ($item->expiration_date) {
                $daysToExpiry = now()->diffInDays($item->expiration_date, false);
            }

            [$baseQuantity, $baseUnit] = $this->resolveBaseQuantity($item);

            $key = $item->product_id . '|' . $item->brand_id;
            $committedForGroup = $groupCommitted[$key] ?? 0;
            // Disponible de ESTE lote: no puede superar ni su propio físico ni el
            // disponible agregado de producto+marca (físico total − comprometido) —
            // así el desplegable nunca ofrece más de lo que el backend acepta.
            $availableForRow = min($baseQuantity, $groupAvailable[$key] ?? $baseQuantity);

            $isExpired = $item->status === 'expired' || ($daysToExpiry !== null && $daysToExpiry < 0);
            $expiredLabel = $isExpired ? ' [VENCIDO]' : '';

            return [
                'inventory_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'brand_id' => $item->brand_id,
                'brand_name' => $item->brand ? $item->brand->name : 'Sin marca',
                'batch_number' => $item->batch_number,
                'expiration_date' => $expirationDate,
                'expiration_date_raw' => $item->expiration_date ? $item->expiration_date->format('Y-m-d') : null,
                'days_to_expiry' => $daysToExpiry,
                'is_expired' => $isExpired,
                'status' => $item->status,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'base_quantity' => $baseQuantity,
                'base_unit' => $baseUnit,
                // Comprometido por otras salidas aprobadas/en tránsito/parciales aún no
                // recibidas (mismo producto+marca, en toda la ubicación) y lo que
                // realmente queda disponible para ESTE lote.
                'committed_quantity' => round($committedForGroup, 2),
                'available_quantity' => round($availableForRow, 2),
                'unit_price' => $item->unit_price,
                'category' => $item->product->category?->name,
                'category_id' => $item->product->category_id,
                'active_ingredient' => $item->product->active_ingredient,
                // Display label for dropdown: "ProductName [code] - Brand - ExpDate - Disponible"
                'display_label' => sprintf(
                    '%s%s%s - %s - %s %s disponible%s',
                    $item->product->name,
                    $item->product->product_code ? ' [' . $item->product->product_code . ']' : '',
                    $item->brand && $item->brand->name ? ' - ' . $item->brand->name : '',
                    $expirationDate,
                    number_format($availableForRow, 2),
                    $baseUnit,
                    $expiredLabel
                ),
                // Short label for mobile (incluye código para búsqueda)
                'short_label' => sprintf(
                    '%s%s - %s - %s %s%s',
                    $item->product->name,
                    $item->product->product_code ? ' [' . $item->product->product_code . ']' : '',
                    $expirationDate,
                    number_format($availableForRow, 2),
                    $baseUnit,
                    $expiredLabel
                ),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Productos para salidas obtenidos exitosamente',
            'data' => $formattedData,
            'count' => $formattedData->count(),
        ]);
    }

    /**
     * Cantidad física de un lote de inventario convertida a la unidad base del
     * producto (p. ej. "2 Bultos" -> 100 kg), y esa unidad. Extraído para que la
     * agregación por [producto, marca] (comprometido/disponible) y el formateo
     * final de cada fila usen EXACTAMENTE la misma conversión.
     *
     * @return array{0: float, 1: string} [cantidad_en_unidad_base, unidad_base]
     */
    private function resolveBaseQuantity(Inventory $item): array
    {
        $baseQuantity = (float) $item->quantity;
        $baseUnit = $item->unit;

        if ($item->product && $item->product->packagingUnits) {
            $matchingPackagingUnit = $item->product->packagingUnits->first(function ($pu) use ($item) {
                return strtolower($pu->name) === strtolower($item->unit);
            });

            if ($matchingPackagingUnit) {
                $baseQuantity = $item->quantity * $matchingPackagingUnit->base_quantity;
                $baseUnit = $matchingPackagingUnit->base_unit;
            }
        }

        return [$baseQuantity, $baseUnit];
    }
}
