<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brandId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'name.unique' => 'Ya existe una marca con este nombre',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
