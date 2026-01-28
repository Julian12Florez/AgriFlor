<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBaseUnitRequest;
use App\Http\Requests\UpdateBaseUnitRequest;
use App\Http\Resources\BaseUnitResource;
use App\Models\BaseUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BaseUnitController extends Controller
{
    /**
     * Display a listing of base units
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BaseUnit::query();

        // Search by name or symbol
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $baseUnits = $query->orderBy('name', 'asc')->paginate($perPage);

        return BaseUnitResource::collection($baseUnits);
    }

    /**
     * Store a newly created base unit
     */
    public function store(StoreBaseUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $baseUnit = BaseUnit::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Unidad base creada exitosamente',
            'data' => new BaseUnitResource($baseUnit)
        ], 201);
    }

    /**
     * Display the specified base unit
     */
    public function show(string $id): JsonResponse
    {
        $baseUnit = BaseUnit::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new BaseUnitResource($baseUnit)
        ]);
    }

    /**
     * Update the specified base unit
     */
    public function update(UpdateBaseUnitRequest $request, string $id): JsonResponse
    {
        $baseUnit = BaseUnit::findOrFail($id);
        $data = $request->validated();

        $baseUnit->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Unidad base actualizada exitosamente',
            'data' => new BaseUnitResource($baseUnit)
        ]);
    }

    /**
     * Remove the specified base unit
     */
    public function destroy(string $id): JsonResponse
    {
        $baseUnit = BaseUnit::findOrFail($id);
        $baseUnit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unidad base eliminada exitosamente'
        ]);
    }
}
