<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmLot;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmLotController extends Controller
{
    /**
     * Display a listing of farm lots
     */
    public function index(Request $request): JsonResponse
    {
        $query = FarmLot::with('location');

        // Filter by location (farm)
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
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
        $lots = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $lots
        ]);
    }

    /**
     * Store a newly created farm lot
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        // Verify the location is a farm
        $location = Location::findOrFail($validated['location_id']);
        if ($location->type !== 'farm') {
            return response()->json([
                'success' => false,
                'message' => 'Los lotes solo pueden crearse para ubicaciones de tipo finca'
            ], 422);
        }

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['area_unit'] = $validated['area_unit'] ?? 'hectares';

        $lot = FarmLot::create($validated);
        $lot->load('location');

        return response()->json([
            'success' => true,
            'message' => 'Lote creado exitosamente',
            'data' => $lot
        ], 201);
    }

    /**
     * Display the specified farm lot
     */
    public function show(string $id): JsonResponse
    {
        $lot = FarmLot::with('location')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $lot
        ]);
    }

    /**
     * Update the specified farm lot
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lot = FarmLot::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $lot->update($validated);
        $lot->load('location');

        return response()->json([
            'success' => true,
            'message' => 'Lote actualizado exitosamente',
            'data' => $lot
        ]);
    }

    /**
     * Remove the specified farm lot
     */
    public function destroy(string $id): JsonResponse
    {
        $lot = FarmLot::findOrFail($id);

        // Check if lot is used in any product output
        if ($lot->productOutputs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el lote porque está asociado a salidas de productos'
            ], 422);
        }

        $lot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lote eliminado exitosamente'
        ]);
    }

    /**
     * Get all lots for a specific farm location
     */
    public function getByLocation(string $locationId): JsonResponse
    {
        $location = Location::findOrFail($locationId);

        if ($location->type !== 'farm') {
            return response()->json([
                'success' => false,
                'message' => 'La ubicación especificada no es una finca'
            ], 422);
        }

        $lots = FarmLot::where('location_id', $locationId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lots
        ]);
    }
}
