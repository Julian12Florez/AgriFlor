<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('tasks', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'duration_hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'daily_cost' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la tarea es requerido',
            'code.unique' => 'El código de la tarea ya existe',
            'code.max' => 'El código no puede exceder 50 caracteres',
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'duration_hours.required' => 'La duración en horas es requerida',
            'duration_hours.numeric' => 'La duración debe ser un número',
            'duration_hours.min' => 'La duración debe ser mayor a 0',
            'duration_hours.max' => 'La duración no puede exceder 24 horas',
            'daily_cost.required' => 'El costo diario es requerido',
            'daily_cost.numeric' => 'El costo diario debe ser un número',
            'daily_cost.min' => 'El costo diario no puede ser negativo',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
