<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlertRequest;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    /**
     * Display a listing of alerts with filters
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Alert::query()->with(['location', 'product', 'resolver']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Default to showing active alerts first
        $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 END")
            ->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $alerts = $query->paginate($perPage);

        return AlertResource::collection($alerts);
    }

    /**
     * Store a newly created alert
     */
    public function store(StoreAlertRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = 'active';

        $alert = Alert::create($data);
        $alert->load(['location', 'product']);

        return response()->json([
            'success' => true,
            'message' => 'Alerta creada exitosamente',
            'data' => new AlertResource($alert)
        ], 201);
    }

    /**
     * Display the specified alert
     */
    public function show(string $id): JsonResponse
    {
        $alert = Alert::with(['location', 'product', 'resolver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new AlertResource($alert)
        ]);
    }

    /**
     * Mark alert as resolved
     */
    public function resolve(string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        if ($alert->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden resolver alertas activas'
            ], 400);
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $alert->load(['location', 'product', 'resolver']);

        return response()->json([
            'success' => true,
            'message' => 'Alerta resuelta exitosamente',
            'data' => new AlertResource($alert)
        ]);
    }

    /**
     * Mark alert as dismissed
     */
    public function dismiss(string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        if ($alert->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden descartar alertas activas'
            ], 400);
        }

        $alert->update([
            'status' => 'dismissed',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $alert->load(['location', 'product', 'resolver']);

        return response()->json([
            'success' => true,
            'message' => 'Alerta descartada exitosamente',
            'data' => new AlertResource($alert)
        ]);
    }
}
