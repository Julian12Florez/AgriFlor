<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'base_quantity' => ['required', 'numeric', 'gt:0'],
            'base_unit' => ['required', 'string', 'exists:base_units,symbol'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no puede exceder 100 caracteres',
            'base_quantity.required' => 'La cantidad base es requerida',
            'base_quantity.numeric' => 'La cantidad base debe ser un número',
            'base_quantity.gt' => 'La cantidad base debe ser mayor a 0',
            'base_unit.required' => 'La unidad base es requerida',
            'base_unit.exists' => 'La unidad base seleccionada no existe',
        ];
    }
}
