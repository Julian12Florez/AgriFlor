<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Display a listing of brands
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Brand::query()->withCount('products');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        $brands = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return BrandResource::collection($brands);
    }

    /**
     * Store a newly created brand
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $brand = Brand::create($data);
        $brand->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => 'Marca creada exitosamente',
            'data' => new BrandResource($brand)
        ], 201);
    }

    /**
     * Display the specified brand
     */
    public function show(string $id): JsonResponse
    {
        $brand = Brand::withCount('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new BrandResource($brand)
        ]);
    }

    /**
     * Update the specified brand
     */
    public function update(UpdateBrandRequest $request, string $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $data = $request->validated();

        $brand->update($data);
        $brand->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => 'Marca actualizada exitosamente',
            'data' => new BrandResource($brand)
        ]);
    }

    /**
     * Remove the specified brand
     */
    public function destroy(string $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marca eliminada exitosamente'
        ]);
    }
}
