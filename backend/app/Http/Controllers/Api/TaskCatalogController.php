<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskCatalogResource;
use App\Models\TaskCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskCatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = TaskCatalog::with('categoryRelation');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($request->filled('unit')) {
            $query->where('unit', $request->unit);
        }

        $perPage = $request->get('per_page', 50);
        $tasks = $query->orderBy('code')->paginate($perPage);

        return TaskCatalogResource::collection($tasks);
    }

    public function show(string $id): JsonResponse
    {
        $task = TaskCatalog::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new TaskCatalogResource($task),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_catalog,name'],
            'unit' => ['required', Rule::in(['arbol', 'hectarea', 'm2', 'metro'])],
            'category' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid', 'exists:task_categories,id'],
            'description' => ['nullable', 'string'],
            'reference_yield' => ['nullable', 'numeric', 'min:0.01'],
            'override_sobrepaso_pct' => ['nullable', 'numeric', 'min:100'],
            'override_alto_pct' => ['nullable', 'numeric', 'min:1'],
            'override_medio_pct' => ['nullable', 'numeric', 'min:1'],
            'override_k_factor' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ]);

        // Generar codigo automatico TA-XXX
        $lastCode = TaskCatalog::orderByRaw("CAST(SUBSTRING(code, 4) AS UNSIGNED) DESC")->first();
        $nextNum = $lastCode ? (int) substr($lastCode->code, 3) + 1 : 1;
        $validated['code'] = 'TA-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        $validated['active'] = true;
        $validated['created_by'] = auth()->id();

        $task = TaskCatalog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tarea creada exitosamente',
            'data' => new TaskCatalogResource($task),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $task = TaskCatalog::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('task_catalog')->ignore($task->id)],
            'unit' => ['sometimes', Rule::in(['arbol', 'hectarea', 'm2', 'metro'])],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'reference_yield' => ['nullable', 'numeric', 'min:0.01'],
            'active' => ['sometimes', 'boolean'],
            'override_sobrepaso_pct' => ['nullable', 'numeric', 'min:100'],
            'override_alto_pct' => ['nullable', 'numeric', 'min:1'],
            'override_medio_pct' => ['nullable', 'numeric', 'min:1'],
            'override_k_factor' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ]);

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tarea actualizada exitosamente',
            'data' => new TaskCatalogResource($task->fresh()),
        ]);
    }

    public function toggleActive(string $id): JsonResponse
    {
        $task = TaskCatalog::findOrFail($id);

        // Bloquear desactivación si tiene programaciones activas
        if ($task->active) {
            $activeSchedules = $task->schedules()
                ->whereIn('status', ['planificada', 'en_progreso'])
                ->count();
            if ($activeSchedules > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede desactivar: tiene {$activeSchedules} programaciones activas (planificadas o en progreso).",
                ], 422);
            }
        }

        $task->update(['active' => !$task->active]);
        $status = $task->active ? 'activada' : 'desactivada';

        return response()->json([
            'success' => true,
            'message' => "Tarea {$status} exitosamente",
            'data' => new TaskCatalogResource($task->fresh()),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = TaskCatalog::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
