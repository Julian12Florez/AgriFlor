<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskId = $this->route('id');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('tasks', 'code')->ignore($taskId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'duration_hours' => ['sometimes', 'numeric', 'min:0.01', 'max:24'],
            'daily_cost' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código de la tarea ya existe',
            'code.max' => 'El código no puede exceder 50 caracteres',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'duration_hours.numeric' => 'La duración debe ser un número',
            'duration_hours.min' => 'La duración debe ser mayor a 0',
            'duration_hours.max' => 'La duración no puede exceder 24 horas',
            'daily_cost.numeric' => 'El costo diario debe ser un número',
            'daily_cost.min' => 'El costo diario no puede ser negativo',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
