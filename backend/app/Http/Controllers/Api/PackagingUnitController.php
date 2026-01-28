<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackagingUnitRequest;
use App\Http\Requests\UpdatePackagingUnitRequest;
use App\Http\Resources\PackagingUnitResource;
use App\Models\PackagingUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackagingUnitController extends Controller
{
    /**
     * Display a listing of packaging units
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PackagingUnit::query();

        // Filter by base_unit
        if ($request->has('base_unit')) {
            $query->where('base_unit', $request->base_unit);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        $packagingUnits = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return PackagingUnitResource::collection($packagingUnits);
    }

    /**
     * Store a newly created packaging unit
     */
    public function store(StorePackagingUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $packagingUnit = PackagingUnit::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Unidad de empaque creada exitosamente',
            'data' => new PackagingUnitResource($packagingUnit)
        ], 201);
    }

    /**
     * Display the specified packaging unit
     */
    public function show(string $id): JsonResponse
    {
        $packagingUnit = PackagingUnit::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PackagingUnitResource($packagingUnit)
        ]);
    }

    /**
     * Update the specified packaging unit
     */
    public function update(UpdatePackagingUnitRequest $request, string $id): JsonResponse
    {
        $packagingUnit = PackagingUnit::findOrFail($id);
        $data = $request->validated();

        $packagingUnit->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Unidad de empaque actualizada exitosamente',
            'data' => new PackagingUnitResource($packagingUnit)
        ]);
    }

    /**
     * Remove the specified packaging unit
     */
    public function destroy(string $id): JsonResponse
    {
        $packagingUnit = PackagingUnit::findOrFail($id);
        $packagingUnit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unidad de empaque eliminada exitosamente'
        ]);
    }
}
