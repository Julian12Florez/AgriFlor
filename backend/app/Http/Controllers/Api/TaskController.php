<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskDeduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Task::query()->with(['deductions', 'activeDeductions']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $task = Task::create($data);
        $task->load(['deductions', 'activeDeductions']);

        return response()->json([
            'success' => true,
            'message' => 'Tarea creada exitosamente',
            'data' => new TaskResource($task)
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $task = Task::with(['deductions', 'activeDeductions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new TaskResource($task)
        ]);
    }

    public function update(UpdateTaskRequest $request, string $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $task->update($request->validated());
        $task->load(['deductions', 'activeDeductions']);

        return response()->json([
            'success' => true,
            'message' => 'Tarea actualizada exitosamente',
            'data' => new TaskResource($task)
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        if ($task->dailyAssignments()->exists()) {
            $task->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Tarea desactivada (tiene asignaciones asociadas)'
            ]);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarea eliminada exitosamente'
        ]);
    }

    public function getNetAmount(string $id): JsonResponse
    {
        $task = Task::with('activeDeductions')->findOrFail($id);
        $calculation = $task->calculateNetAmount();

        return response()->json([
            'success' => true,
            'data' => $calculation
        ]);
    }

    // --- Deduction endpoints ---

    public function storeDeduction(Request $request, string $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $request->validate([
            'deduction_name' => ['required', 'string', 'max:255'],
            'percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'deduction_name.required' => 'El nombre de la deducción es requerido',
            'percentage.required' => 'El porcentaje es requerido',
            'percentage.numeric' => 'El porcentaje debe ser un número',
            'percentage.min' => 'El porcentaje debe ser mayor a 0',
            'percentage.max' => 'El porcentaje no puede ser mayor a 100%',
        ]);

        // Validate total deductions don't exceed 100%
        $currentTotal = $task->activeDeductions()->sum('percentage');
        $newPercentage = $request->percentage;
        if (($currentTotal + $newPercentage) > 100) {
            return response()->json([
                'success' => false,
                'message' => 'La suma de deducciones no puede exceder el 100%. Total actual: ' . $currentTotal . '%'
            ], 422);
        }

        $deduction = $task->deductions()->create([
            'deduction_name' => $request->deduction_name,
            'percentage' => $request->percentage,
            'is_active' => $request->is_active ?? true,
        ]);

        $task->load(['deductions', 'activeDeductions']);

        return response()->json([
            'success' => true,
            'message' => 'Deducción agregada exitosamente',
            'data' => new TaskResource($task)
        ], 201);
    }

    public function updateDeduction(Request $request, string $id, string $deductionId): JsonResponse
    {
        $task = Task::findOrFail($id);
        $deduction = TaskDeduction::where('task_id', $id)->findOrFail($deductionId);

        $request->validate([
            'deduction_name' => ['sometimes', 'string', 'max:255'],
            'percentage' => ['sometimes', 'numeric', 'min:0.01', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Validate total deductions don't exceed 100%
        if ($request->has('percentage') || $request->has('is_active')) {
            $newPercentage = $request->percentage ?? $deduction->percentage;
            $newIsActive = $request->is_active ?? $deduction->is_active;

            $currentTotal = $task->activeDeductions()
                ->where('id', '!=', $deductionId)
                ->sum('percentage');

            if ($newIsActive && ($currentTotal + $newPercentage) > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'La suma de deducciones no puede exceder el 100%'
                ], 422);
            }
        }

        $deduction->update($request->only(['deduction_name', 'percentage', 'is_active']));
        $task->load(['deductions', 'activeDeductions']);

        return response()->json([
            'success' => true,
            'message' => 'Deducción actualizada exitosamente',
            'data' => new TaskResource($task)
        ]);
    }

    public function destroyDeduction(string $id, string $deductionId): JsonResponse
    {
        Task::findOrFail($id);
        $deduction = TaskDeduction::where('task_id', $id)->findOrFail($deductionId);
        $deduction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deducción eliminada exitosamente'
        ]);
    }

    /**
     * Lightweight list for dropdowns
     */
    public function listSimple(Request $request): JsonResponse
    {
        $query = Task::query()->select('id', 'code', 'name', 'daily_cost', 'status');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->orderBy('name')->get();

        return response()->json([
            'data' => $tasks
        ]);
    }
}
