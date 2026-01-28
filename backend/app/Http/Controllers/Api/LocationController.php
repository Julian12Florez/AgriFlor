<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    /**
     * Display a listing of locations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Location::with('responsibleUser');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, municipality, address, or responsible user name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('municipality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('responsibleUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $locations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return LocationResource::collection($locations);
    }

    /**
     * Display a listing of warehouses
     */
    public function warehouses(Request $request): AnonymousResourceCollection
    {
        $query = Location::with('responsibleUser')->where('type', 'warehouse');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, municipality, address, or responsible user name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('municipality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('responsibleUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $locations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return LocationResource::collection($locations);
    }

    /**
     * Display a listing of farms
     */
    public function farms(Request $request): AnonymousResourceCollection
    {
        $query = Location::with('responsibleUser')->where('type', 'farm');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, municipality, address, or responsible user name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('municipality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('responsibleUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $locations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return LocationResource::collection($locations);
    }

    /**
     * Store a newly created location
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $location = Location::create($data);
        $location->load('responsibleUser');

        return response()->json([
            'success' => true,
            'message' => 'Ubicación creada exitosamente',
            'data' => new LocationResource($location)
        ], 201);
    }

    /**
     * Display the specified location
     */
    public function show(string $id): JsonResponse
    {
        $location = Location::with('responsibleUser')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new LocationResource($location)
        ]);
    }

    /**
     * Update the specified location
     */
    public function update(UpdateLocationRequest $request, string $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $data = $request->validated();

        $location->update($data);
        $location->load('responsibleUser');

        return response()->json([
            'success' => true,
            'message' => 'Ubicación actualizada exitosamente',
            'data' => new LocationResource($location)
        ]);
    }

    /**
     * Remove the specified location
     */
    public function destroy(string $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ubicación eliminada exitosamente'
        ]);
    }
}
