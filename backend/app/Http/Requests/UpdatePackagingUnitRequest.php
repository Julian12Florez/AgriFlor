<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackagingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'base_quantity' => ['sometimes', 'numeric', 'gt:0'],
            'base_unit' => ['sometimes', 'string', 'exists:base_units,symbol'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 100 caracteres',
            'base_quantity.numeric' => 'La cantidad base debe ser un número',
            'base_quantity.gt' => 'La cantidad base debe ser mayor a 0',
            'base_unit.exists' => 'La unidad base seleccionada no existe',
        ];
    }
}
