<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderId = $this->route('technicalOrder');

        return [
            'order_number' => [
                'sometimes',
                'string',
                Rule::unique('technical_orders', 'order_number')->ignore($orderId)
            ],
            'scheduled_date' => ['sometimes', 'date'],
            'farm_ids' => ['sometimes', 'array', 'min:1'],
            'farm_ids.*' => ['required', 'uuid', 'exists:locations,id'],
            'recipe_id' => ['nullable', 'uuid', 'exists:technical_recipes,id'],
            'products' => ['sometimes', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'products.*.brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.unit' => ['required', 'string'],
            'products.*.observations' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'responsible_agronomist' => ['sometimes', 'uuid', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_number.unique' => 'El número de orden ya existe',
            'scheduled_date.date' => 'La fecha programada debe ser una fecha válida',
            'farm_ids.array' => 'Las fincas deben ser un arreglo',
            'farm_ids.min' => 'Debe seleccionar al menos una finca',
            'farm_ids.*.required' => 'Cada finca es requerida',
            'farm_ids.*.uuid' => 'El formato de la finca no es válido',
            'farm_ids.*.exists' => 'Una o más fincas seleccionadas no existen',
            'recipe_id.uuid' => 'El formato de la receta no es válido',
            'recipe_id.exists' => 'La receta seleccionada no existe',
            'products.array' => 'Los productos deben ser un arreglo',
            'products.min' => 'Debe agregar al menos un producto',
            'products.*.product_id.required' => 'El producto es requerido',
            'products.*.product_id.uuid' => 'El formato del producto no es válido',
            'products.*.product_id.exists' => 'Uno o más productos seleccionados no existen',
            'products.*.brand_id.required' => 'La marca es requerida',
            'products.*.brand_id.uuid' => 'El formato de la marca no es válido',
            'products.*.brand_id.exists' => 'Una o más marcas seleccionadas no existen',
            'products.*.quantity.required' => 'La cantidad es requerida',
            'products.*.quantity.numeric' => 'La cantidad debe ser un número',
            'products.*.quantity.gt' => 'La cantidad debe ser mayor a cero',
            'products.*.unit.required' => 'La unidad es requerida',
            'responsible_agronomist.uuid' => 'El formato del agrónomo no es válido',
            'responsible_agronomist.exists' => 'El agrónomo seleccionado no existe',
        ];
    }
}
