<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicalRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(['fertilization', 'pest_control', 'disease_control', 'weed_control', 'other'])],
            'application_instructions' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'products.*.brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.unit' => ['required', 'string'],
            'products.*.application_rate' => ['nullable', 'string'],
            'products.*.observations' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'category.required' => 'La categoría es requerida',
            'category.in' => 'La categoría seleccionada no es válida',
            'products.required' => 'Debe agregar al menos un producto',
            'products.array' => 'Los productos deben ser un arreglo',
            'products.min' => 'Debe agregar al menos un producto',
            'products.*.product_id.required' => 'El producto es requerido',
            'products.*.product_id.uuid' => 'El formato del producto no es válido',
            'products.*.product_id.exists' => 'El producto seleccionado no existe',
            'products.*.brand_id.required' => 'La marca es requerida',
            'products.*.brand_id.uuid' => 'El formato de la marca no es válido',
            'products.*.brand_id.exists' => 'La marca seleccionada no existe',
            'products.*.quantity.required' => 'La cantidad es requerida',
            'products.*.quantity.numeric' => 'La cantidad debe ser un número',
            'products.*.quantity.gt' => 'La cantidad debe ser mayor a 0',
            'products.*.unit.required' => 'La unidad es requerida',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
