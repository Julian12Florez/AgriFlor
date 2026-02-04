<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()->withCount('products');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $categories = $query->orderBy('name', 'asc')->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $category = Category::create($data);
        $category->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada exitosamente',
            'data' => new CategoryResource($category)
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::withCount('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();

        $category->update($data);
        $category->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente',
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::withCount('products')->findOrFail($id);

        // Prevent deletion if category has products
        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente'
        ]);
    }
}
