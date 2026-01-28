<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['error', 'warning', 'info', 'success'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'severity' => ['required', Rule::in(['high', 'medium', 'low'])],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de alerta es requerido',
            'type.in' => 'El tipo de alerta seleccionado no es válido',
            'title.required' => 'El título es requerido',
            'title.string' => 'El título debe ser un texto',
            'title.max' => 'El título no puede exceder 255 caracteres',
            'description.string' => 'La descripción debe ser un texto',
            'location_id.uuid' => 'El formato de la ubicación no es válido',
            'location_id.exists' => 'La ubicación seleccionada no existe',
            'product_id.uuid' => 'El formato del producto no es válido',
            'product_id.exists' => 'El producto seleccionado no existe',
            'severity.required' => 'La severidad es requerida',
            'severity.in' => 'La severidad seleccionada no es válida',
        ];
    }
}
