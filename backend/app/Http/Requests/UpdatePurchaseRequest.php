<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $purchaseId = $this->route('purchase');

        return [
            'order_number' => [
                'sometimes',
                'string',
                Rule::unique('purchases', 'order_number')->ignore($purchaseId)
            ],
            'company_id' => ['sometimes', 'uuid', 'exists:companies,id'],
            'supplier_id' => ['sometimes', 'uuid', 'exists:suppliers,id'],
            'origin_location_id' => ['nullable', 'uuid', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['sometimes', 'uuid', 'exists:locations,id'],
            'purchase_date' => ['sometimes', 'date'],
            'expected_delivery' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'items.*.packaging_unit_id' => ['required', 'uuid', 'exists:packaging_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.expiration_date' => ['nullable', 'date', 'after:today'],
            'observations' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_number.unique' => 'El número de orden ya existe',
            'supplier_id.uuid' => 'El formato del proveedor no es válido',
            'supplier_id.exists' => 'El proveedor seleccionado no existe',
            'origin_location_id.uuid' => 'El formato de la ubicación de origen no es válido',
            'origin_location_id.exists' => 'La ubicación de origen seleccionada no existe',
            'origin_location_id.different' => 'La ubicación de origen debe ser diferente a la ubicación de destino',
            'destination_location_id.uuid' => 'El formato de la ubicación no es válido',
            'destination_location_id.exists' => 'La ubicación seleccionada no existe',
            'purchase_date.date' => 'La fecha de compra debe ser una fecha válida',
            'expected_delivery.date' => 'La fecha de entrega esperada debe ser una fecha válida',
            'expected_delivery.after_or_equal' => 'La fecha de entrega esperada debe ser igual o posterior a la fecha de compra',
            'items.array' => 'Los productos deben ser un arreglo',
            'items.min' => 'Debe agregar al menos un producto',
            'items.*.product_id.required' => 'El producto es requerido',
            'items.*.product_id.uuid' => 'El formato del producto no es válido',
            'items.*.product_id.exists' => 'Uno o más productos seleccionados no existen',
            'items.*.brand_id.required' => 'La marca es requerida',
            'items.*.brand_id.uuid' => 'El formato de la marca no es válido',
            'items.*.brand_id.exists' => 'Una o más marcas seleccionadas no existen',
            'items.*.packaging_unit_id.required' => 'La unidad de empaque es requerida',
            'items.*.packaging_unit_id.uuid' => 'El formato de la unidad de empaque no es válido',
            'items.*.packaging_unit_id.exists' => 'Una o más unidades de empaque seleccionadas no existen',
            'items.*.quantity.required' => 'La cantidad es requerida',
            'items.*.quantity.numeric' => 'La cantidad debe ser un número',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a cero',
            'items.*.unit_price.required' => 'El precio unitario es requerido',
            'items.*.unit_price.numeric' => 'El precio unitario debe ser un número',
            'items.*.unit_price.gte' => 'El precio unitario debe ser mayor o igual a cero',
            'items.*.expiration_date.date' => 'La fecha de vencimiento debe ser una fecha válida',
            'items.*.expiration_date.after' => 'La fecha de vencimiento debe ser posterior a hoy',
            'attachments.array' => 'Los archivos adjuntos deben ser un arreglo',
            'attachments.*.file' => 'Cada adjunto debe ser un archivo válido',
            'attachments.*.mimes' => 'Los archivos deben ser de tipo: pdf, jpg, jpeg, png, doc, docx, xls, xlsx',
            'attachments.*.max' => 'Cada archivo no puede exceder 10MB',
        ];
    }
}
