<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'product_code' => ['nullable', 'string', 'max:100', Rule::unique('products', 'product_code')->ignore($productId)],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'base_unit' => ['nullable', 'string', 'exists:base_units,symbol'],
            'active_ingredient' => ['sometimes', 'string'],
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'brand_id' => ['sometimes', 'uuid', 'exists:brands,id'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
            'iva' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'packaging_unit_ids' => ['nullable', 'array'],
            'packaging_unit_ids.*' => ['uuid', 'exists:packaging_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'product_code.max' => 'El código de producto no puede exceder 100 caracteres',
            'product_code.unique' => 'El código de producto ya existe',
            'category_id.uuid' => 'El formato de la categoría no es válido',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'base_unit.exists' => 'La unidad base seleccionada no existe',
            'min_stock.numeric' => 'El stock mínimo debe ser un número',
            'min_stock.min' => 'El stock mínimo no puede ser negativo',
            'brand_id.uuid' => 'El formato de la marca no es válido',
            'brand_id.exists' => 'La marca seleccionada no existe',
            'status.in' => 'El estado seleccionado no es válido',
            'iva.integer' => 'El IVA debe ser un número entero',
            'iva.min' => 'El IVA no puede ser negativo',
            'iva.max' => 'El IVA no puede ser mayor a 100%',
        ];
    }
}
