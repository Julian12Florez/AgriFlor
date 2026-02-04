<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
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
                'category' => $product->category_name, // Uses accessor for backward compatibility
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
     * Returns products ordered by expiration date (FIFO) and then by stock
     */
    public function getForOutputs(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|uuid|exists:locations,id',
            'search' => 'nullable|string',
        ]);

        // Get all inventory items for the location (exclude only expired)
        $inventoryItems = Inventory::where('location_id', $request->location_id)
            ->whereNotIn('status', ['expired'])
            ->where('quantity', '>', 0)
            ->with(['product.packagingUnits', 'brand'])
            ->orderBy('expiration_date', 'asc') // FIFO: Expiration date first
            ->orderBy('quantity', 'desc') // Then by stock
            ->get();

        // Apply search filter if provided
        if ($request->search) {
            $search = strtolower($request->search);
            $inventoryItems = $inventoryItems->filter(function ($item) use ($search) {
                return str_contains(strtolower($item->product->name), $search) ||
                       str_contains(strtolower($item->product->active_ingredient ?? ''), $search) ||
                       str_contains(strtolower($item->brand->name ?? ''), $search);
            });
        }

        // Format data for frontend dropdown
        $formattedData = $inventoryItems->map(function ($item) {
            $expirationDate = $item->expiration_date
                ? $item->expiration_date->format('d/m/Y')
                : 'Sin vencimiento';

            $daysToExpiry = null;
            if ($item->expiration_date) {
                $daysToExpiry = now()->diffInDays($item->expiration_date, false);
            }

            // Calculate converted quantity to base unit
            $baseQuantity = $item->quantity;
            $baseUnit = $item->unit;

            // Find the packaging unit that matches the inventory unit
            if ($item->product && $item->product->packagingUnits) {
                $matchingPackagingUnit = $item->product->packagingUnits->first(function ($pu) use ($item) {
                    return strtolower($pu->name) === strtolower($item->unit);
                });

                if ($matchingPackagingUnit) {
                    $baseQuantity = $item->quantity * $matchingPackagingUnit->base_quantity;
                    $baseUnit = $matchingPackagingUnit->base_unit;
                }
            }

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
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'base_quantity' => $baseQuantity,
                'base_unit' => $baseUnit,
                'unit_price' => $item->unit_price,
                'category' => $item->product->category_name, // Uses accessor for backward compatibility
                'category_id' => $item->product->category_id,
                'active_ingredient' => $item->product->active_ingredient,
                // Display label for dropdown: "ProductName - ExpDate - ConvertedStock"
                'display_label' => sprintf(
                    '%s - %s - %s %s disponible',
                    $item->product->name,
                    $expirationDate,
                    number_format($baseQuantity, 2),
                    $baseUnit
                ),
                // Short label for mobile
                'short_label' => sprintf(
                    '%s - %s - %s %s',
                    $item->product->name,
                    $expirationDate,
                    number_format($baseQuantity, 2),
                    $baseUnit
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
}
