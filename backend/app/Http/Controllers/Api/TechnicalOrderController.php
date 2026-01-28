<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicalOrderRequest;
use App\Http\Requests\UpdateTechnicalOrderRequest;
use App\Http\Resources\TechnicalOrderResource;
use App\Models\TechnicalOrder;
use App\Models\TechnicalOrderProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TechnicalOrderController extends Controller
{
    /**
     * Display a listing of technical orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TechnicalOrder::query()
            ->with(['farms', 'technicalOrderProducts.product', 'technicalOrderProducts.brand', 'recipe', 'agronomist', 'applier']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by farm
        if ($request->has('farm_id')) {
            $query->whereHas('farms', function ($q) use ($request) {
                $q->where('farm_id', $request->farm_id);
            });
        }

        // Search by order_number or observations
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('observations', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('scheduled_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('scheduled_date', '<=', $request->date_to);
        }

        // Filter by agronomist
        if ($request->has('responsible_agronomist')) {
            $query->where('responsible_agronomist', $request->responsible_agronomist);
        }

        $perPage = $request->get('per_page', 15);
        $technicalOrders = $query->orderBy('scheduled_date', 'desc')->paginate($perPage);

        return TechnicalOrderResource::collection($technicalOrders);
    }

    /**
     * Store a newly created technical order
     */
    public function store(StoreTechnicalOrderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Create the technical order
            $technicalOrder = TechnicalOrder::create([
                'order_number' => $data['order_number'],
                'scheduled_date' => $data['scheduled_date'],
                'status' => 'draft',
                'recipe_id' => $data['recipe_id'] ?? null,
                'responsible_agronomist' => $data['responsible_agronomist'],
                'observations' => $data['observations'] ?? null,
                'estimated_cost' => 0, // Will be calculated
            ]);

            // Attach farms
            $technicalOrder->farms()->attach($data['farm_ids']);

            // Create technical order products
            $totalCost = 0;
            foreach ($data['products'] as $productData) {
                TechnicalOrderProduct::create([
                    'technical_order_id' => $technicalOrder->id,
                    'product_id' => $productData['product_id'],
                    'brand_id' => $productData['brand_id'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'observations' => $productData['observations'] ?? null,
                ]);
            }

            DB::commit();

            // Load relationships
            $technicalOrder->load([
                'farms',
                'technicalOrderProducts.product',
                'technicalOrderProducts.brand',
                'recipe',
                'agronomist',
                'applier'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica creada exitosamente',
                'data' => new TechnicalOrderResource($technicalOrder)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified technical order
     */
    public function show(string $id): JsonResponse
    {
        $technicalOrder = TechnicalOrder::with([
            'farms',
            'technicalOrderProducts.product',
            'technicalOrderProducts.brand',
            'recipe',
            'agronomist',
            'applier'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new TechnicalOrderResource($technicalOrder)
        ]);
    }

    /**
     * Update the specified technical order
     */
    public function update(UpdateTechnicalOrderRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $technicalOrder = TechnicalOrder::findOrFail($id);
            $data = $request->validated();

            // Update basic fields
            $updateData = [];
            if (isset($data['order_number'])) {
                $updateData['order_number'] = $data['order_number'];
            }
            if (isset($data['scheduled_date'])) {
                $updateData['scheduled_date'] = $data['scheduled_date'];
            }
            if (isset($data['recipe_id'])) {
                $updateData['recipe_id'] = $data['recipe_id'];
            }
            if (isset($data['responsible_agronomist'])) {
                $updateData['responsible_agronomist'] = $data['responsible_agronomist'];
            }
            if (isset($data['observations'])) {
                $updateData['observations'] = $data['observations'];
            }

            $technicalOrder->update($updateData);

            // Update farms if provided
            if (isset($data['farm_ids'])) {
                $technicalOrder->farms()->sync($data['farm_ids']);
            }

            // Update products if provided
            if (isset($data['products'])) {
                // Delete existing products
                $technicalOrder->technicalOrderProducts()->delete();

                // Create new products
                foreach ($data['products'] as $productData) {
                    TechnicalOrderProduct::create([
                        'technical_order_id' => $technicalOrder->id,
                        'product_id' => $productData['product_id'],
                        'brand_id' => $productData['brand_id'],
                        'quantity' => $productData['quantity'],
                        'unit' => $productData['unit'],
                        'observations' => $productData['observations'] ?? null,
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $technicalOrder->load([
                'farms',
                'technicalOrderProducts.product',
                'technicalOrderProducts.brand',
                'recipe',
                'agronomist',
                'applier'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica actualizada exitosamente',
                'data' => new TechnicalOrderResource($technicalOrder)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified technical order
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $technicalOrder = TechnicalOrder::findOrFail($id);

            // Check if order can be deleted (only draft or cancelled)
            if (!in_array($technicalOrder->status, ['draft', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar órdenes en estado borrador o canceladas'
                ], 422);
            }

            // Delete related records
            $technicalOrder->technicalOrderProducts()->delete();
            $technicalOrder->farms()->detach();
            $technicalOrder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a technical order
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $technicalOrder = TechnicalOrder::findOrFail($id);

            // Check if order can be approved (only draft)
            if ($technicalOrder->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden aprobar órdenes en estado borrador'
                ], 422);
            }

            $technicalOrder->update(['status' => 'approved']);

            $technicalOrder->load([
                'farms',
                'technicalOrderProducts.product',
                'technicalOrderProducts.brand',
                'recipe',
                'agronomist',
                'applier'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica aprobada exitosamente',
                'data' => new TechnicalOrderResource($technicalOrder)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete a technical order
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        try {
            $technicalOrder = TechnicalOrder::findOrFail($id);

            // Check if order can be completed (only approved)
            if ($technicalOrder->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden completar órdenes en estado aprobado'
                ], 422);
            }

            $technicalOrder->update([
                'status' => 'completed',
                'applied_at' => now(),
                'applied_by' => $request->user()->id,
            ]);

            $technicalOrder->load([
                'farms',
                'technicalOrderProducts.product',
                'technicalOrderProducts.brand',
                'recipe',
                'agronomist',
                'applier'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica completada exitosamente',
                'data' => new TechnicalOrderResource($technicalOrder)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al completar la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a technical order
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $technicalOrder = TechnicalOrder::findOrFail($id);

            // Check if order can be cancelled (not completed)
            if ($technicalOrder->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden cancelar órdenes completadas'
                ], 422);
            }

            $technicalOrder->update(['status' => 'cancelled']);

            $technicalOrder->load([
                'farms',
                'technicalOrderProducts.product',
                'technicalOrderProducts.brand',
                'recipe',
                'agronomist',
                'applier'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden técnica cancelada exitosamente',
                'data' => new TechnicalOrderResource($technicalOrder)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la orden técnica',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
