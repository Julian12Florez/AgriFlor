<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutputType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutputTypeController extends Controller
{
    /**
     * Display a listing of output types
     */
    public function index(Request $request): JsonResponse
    {
        $query = OutputType::query()->withCount('productOutputs');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by requires_lots
        if ($request->has('requires_lots')) {
            $query->where('requires_lots', $request->boolean('requires_lots'));
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $types = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Store a newly created output type
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:output_types,code'],
            'description' => ['nullable', 'string'],
            'requires_lots' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['requires_lots'] = $validated['requires_lots'] ?? false;

        $type = OutputType::create($validated);
        $type->loadCount('productOutputs');

        return response()->json([
            'success' => true,
            'message' => 'Tipo de salida creado exitosamente',
            'data' => $type
        ], 201);
    }

    /**
     * Display the specified output type
     */
    public function show(string $id): JsonResponse
    {
        $type = OutputType::withCount('productOutputs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $type
        ]);
    }

    /**
     * Update the specified output type
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = OutputType::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('output_types', 'code')->ignore($id)],
            'description' => ['nullable', 'string'],
            'requires_lots' => ['nullable', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $type->update($validated);
        $type->loadCount('productOutputs');

        return response()->json([
            'success' => true,
            'message' => 'Tipo de salida actualizado exitosamente',
            'data' => $type
        ]);
    }

    /**
     * Remove the specified output type
     */
    public function destroy(string $id): JsonResponse
    {
        $type = OutputType::findOrFail($id);

        // Check if type is used in any product output
        if ($type->productOutputs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el tipo de salida porque está siendo utilizado por salidas de productos'
            ], 422);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de salida eliminado exitosamente'
        ]);
    }
}
