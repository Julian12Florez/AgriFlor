<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TaskCategory::withCount('tasks');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $categories = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'description' => $c->description,
                'active' => $c->active,
                'tasksCount' => $c->tasks_count,
                'tasks_count' => $c->tasks_count,
                'createdAt' => $c->created_at?->toISOString(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:task_categories,name'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['active'] = true;
        $category = TaskCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Categoria creada exitosamente',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = TaskCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('task_categories')->ignore($category->id)],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Categoria actualizada exitosamente',
            'data' => $category,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = TaskCategory::withCount('tasks')->findOrFail($id);

        if ($category->tasks_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: tiene {$category->tasks_count} tareas asociadas",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada exitosamente',
        ]);
    }
}
