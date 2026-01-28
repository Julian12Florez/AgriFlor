<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicalRecipeRequest;
use App\Http\Requests\UpdateTechnicalRecipeRequest;
use App\Http\Resources\TechnicalRecipeResource;
use App\Models\RecipeProduct;
use App\Models\TechnicalRecipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TechnicalRecipeController extends Controller
{
    /**
     * Display a listing of technical recipes
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TechnicalRecipe::query()
            ->with(['creator', 'recipeProducts.product', 'recipeProducts.brand']);

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

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
        $recipes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return TechnicalRecipeResource::collection($recipes);
    }

    /**
     * Store a newly created technical recipe
     */
    public function store(StoreTechnicalRecipeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $products = $data['products'];
            unset($data['products']);

            // Set default status and created_by
            $data['status'] = $data['status'] ?? 'active';
            $data['created_by'] = auth()->id();
            $data['usage_count'] = 0;

            // Create the recipe
            $recipe = TechnicalRecipe::create($data);

            // Create recipe products
            foreach ($products as $product) {
                RecipeProduct::create([
                    'recipe_id' => $recipe->id,
                    'product_id' => $product['product_id'],
                    'brand_id' => $product['brand_id'],
                    'quantity' => $product['quantity'],
                    'unit' => $product['unit'],
                    'application_rate' => $product['application_rate'] ?? null,
                    'observations' => $product['observations'] ?? null,
                ]);
            }

            DB::commit();

            $recipe->load(['creator', 'recipeProducts.product', 'recipeProducts.brand']);

            return response()->json([
                'success' => true,
                'message' => 'Receta técnica creada exitosamente',
                'data' => new TechnicalRecipeResource($recipe)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la receta técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified technical recipe
     */
    public function show(string $id): JsonResponse
    {
        $recipe = TechnicalRecipe::with([
            'creator',
            'recipeProducts.product.brand',
            'recipeProducts.brand'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new TechnicalRecipeResource($recipe)
        ]);
    }

    /**
     * Update the specified technical recipe
     */
    public function update(UpdateTechnicalRecipeRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $recipe = TechnicalRecipe::findOrFail($id);
            $data = $request->validated();

            // Extract products if present
            $products = $data['products'] ?? null;
            unset($data['products']);

            // Update recipe basic data
            $recipe->update($data);

            // Update products if provided
            if ($products !== null) {
                // Delete existing products
                RecipeProduct::where('recipe_id', $recipe->id)->delete();

                // Create new products
                foreach ($products as $product) {
                    RecipeProduct::create([
                        'recipe_id' => $recipe->id,
                        'product_id' => $product['product_id'],
                        'brand_id' => $product['brand_id'],
                        'quantity' => $product['quantity'],
                        'unit' => $product['unit'],
                        'application_rate' => $product['application_rate'] ?? null,
                        'observations' => $product['observations'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $recipe->load(['creator', 'recipeProducts.product', 'recipeProducts.brand']);

            return response()->json([
                'success' => true,
                'message' => 'Receta técnica actualizada exitosamente',
                'data' => new TechnicalRecipeResource($recipe)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la receta técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified technical recipe
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $recipe = TechnicalRecipe::findOrFail($id);

            // Delete associated recipe products
            RecipeProduct::where('recipe_id', $recipe->id)->delete();

            // Delete the recipe
            $recipe->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Receta técnica eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la receta técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate an existing technical recipe
     */
    public function duplicate(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $originalRecipe = TechnicalRecipe::with('recipeProducts')->findOrFail($id);

            // Create a copy of the recipe
            $newRecipe = TechnicalRecipe::create([
                'name' => $originalRecipe->name . ' (Copia)',
                'description' => $originalRecipe->description,
                'category' => $originalRecipe->category,
                'application_instructions' => $originalRecipe->application_instructions,
                'safety_notes' => $originalRecipe->safety_notes,
                'status' => 'active',
                'created_by' => auth()->id(),
                'usage_count' => 0,
            ]);

            // Copy recipe products
            foreach ($originalRecipe->recipeProducts as $recipeProduct) {
                RecipeProduct::create([
                    'recipe_id' => $newRecipe->id,
                    'product_id' => $recipeProduct->product_id,
                    'brand_id' => $recipeProduct->brand_id,
                    'quantity' => $recipeProduct->quantity,
                    'unit' => $recipeProduct->unit,
                    'application_rate' => $recipeProduct->application_rate,
                    'observations' => $recipeProduct->observations,
                ]);
            }

            DB::commit();

            $newRecipe->load(['creator', 'recipeProducts.product', 'recipeProducts.brand']);

            return response()->json([
                'success' => true,
                'message' => 'Receta técnica duplicada exitosamente',
                'data' => new TechnicalRecipeResource($newRecipe)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar la receta técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
