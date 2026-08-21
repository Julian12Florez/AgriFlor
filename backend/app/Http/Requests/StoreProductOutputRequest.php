<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Obligatorio aquí aunque la columna sea nullable en BD: lo nuevo
            // debe declarar su empresa emisora, pero el histórico previo al
            // módulo de empresas se conserva tal cual.
            'company_id' => [
                'required',
                'uuid',
                'exists:companies,id'
            ],
            'output_type_id' => [
                'required',
                'uuid',
                'exists:output_types,id'
            ],
            'technical_order_id' => [
                'nullable',
                'uuid',
                'exists:technical_orders,id'
            ],
            'output_date' => [
                'required',
                'date'
            ],
            'origin_location_id' => [
                'required',
                'uuid',
                'exists:locations,id'
            ],
            'destination_location_id' => [
                'required',
                'uuid',
                'exists:locations,id',
                function ($attribute, $value, $fail) {
                    // Get the output type to check if it's consumption
                    $outputTypeId = $this->input('output_type_id');
                    $outputType = \App\Models\OutputType::find($outputTypeId);

                    // For consumption type, origin and destination can be the same
                    if ($outputType && $outputType->code !== 'consumption') {
                        if ($this->input('origin_location_id') === $value) {
                            $fail('La ubicación de destino debe ser diferente a la ubicación de origen.');
                        }
                    }
                }
            ],
            'farm_lot_ids' => [
                'nullable',
                'array'
            ],
            'farm_lot_ids.*' => [
                'uuid',
                'exists:farm_lots,id'
            ],
            'products' => [
                'required',
                'array',
                'min:1'
            ],
            'products.*.product_id' => [
                'required',
                'uuid',
                'exists:products,id'
            ],
            'products.*.brand_id' => [
                'required',
                'uuid',
                'exists:brands,id'
            ],
            'products.*.quantity_requested' => [
                'required',
                'numeric',
                'gt:0'
            ],
            'products.*.quantity_delivered' => [
                'required',
                'numeric',
                'gt:0',
                function ($attribute, $value, $fail) {
                    // Extract the index from the attribute path
                    preg_match('/products\.(\d+)\.quantity_delivered/', $attribute, $matches);
                    if (isset($matches[1])) {
                        $index = $matches[1];
                        $quantityRequested = $this->input("products.{$index}.quantity_requested");

                        if ($quantityRequested && $value > ($quantityRequested * 1.05)) {
                            $fail('La cantidad entregada no puede exceder el 105% de la cantidad solicitada.');
                        }
                    }
                }
            ],
            'products.*.unit' => [
                'required',
                'string',
                'exists:base_units,symbol'
            ],
            'products.*.batch_number' => [
                'nullable',
                'string',
                'max:255'
            ],
            'products.*.expiration_date' => [
                'nullable',
                'date',
                'after:today'
            ],
            'observations' => [
                'nullable',
                'string'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'La empresa emisora es requerida',
            'company_id.uuid' => 'El formato de la empresa no es válido',
            'company_id.exists' => 'La empresa seleccionada no existe',
            'output_type_id.required' => 'El tipo de salida es requerido',
            'output_type_id.uuid' => 'El formato del tipo de salida no es válido',
            'output_type_id.exists' => 'El tipo de salida seleccionado no existe',
            'technical_order_id.uuid' => 'El formato de la orden técnica no es válido',
            'technical_order_id.exists' => 'La orden técnica seleccionada no existe',
            'output_date.required' => 'La fecha de salida es requerida',
            'output_date.date' => 'La fecha de salida no es válida',
            'origin_location_id.required' => 'La ubicación de origen es requerida',
            'origin_location_id.uuid' => 'El formato de la ubicación de origen no es válido',
            'origin_location_id.exists' => 'La ubicación de origen seleccionada no existe',
            'destination_location_id.required' => 'La ubicación de destino es requerida',
            'destination_location_id.uuid' => 'El formato de la ubicación de destino no es válido',
            'destination_location_id.exists' => 'La ubicación de destino seleccionada no existe',
            'farm_lot_ids.array' => 'El formato de los lotes de finca no es válido',
            'farm_lot_ids.*.uuid' => 'El formato del lote de finca no es válido',
            'farm_lot_ids.*.exists' => 'Uno de los lotes de finca seleccionados no existe',
            'products.required' => 'Debe incluir al menos un producto',
            'products.array' => 'El formato de productos no es válido',
            'products.min' => 'Debe incluir al menos un producto',
            'products.*.product_id.required' => 'El producto es requerido',
            'products.*.product_id.uuid' => 'El formato del producto no es válido',
            'products.*.product_id.exists' => 'El producto seleccionado no existe',
            'products.*.brand_id.required' => 'La marca es requerida',
            'products.*.brand_id.uuid' => 'El formato de la marca no es válido',
            'products.*.brand_id.exists' => 'La marca seleccionada no existe',
            'products.*.quantity_requested.required' => 'La cantidad solicitada es requerida',
            'products.*.quantity_requested.numeric' => 'La cantidad solicitada debe ser un número',
            'products.*.quantity_requested.gt' => 'La cantidad solicitada debe ser mayor a 0',
            'products.*.quantity_delivered.required' => 'La cantidad entregada es requerida',
            'products.*.quantity_delivered.numeric' => 'La cantidad entregada debe ser un número',
            'products.*.quantity_delivered.gt' => 'La cantidad entregada debe ser mayor a 0',
            'products.*.unit.required' => 'La unidad es requerida',
            'products.*.unit.exists' => 'La unidad seleccionada no existe',
            'products.*.batch_number.max' => 'El número de lote no puede exceder 255 caracteres',
            'products.*.expiration_date.date' => 'La fecha de vencimiento no es válida',
            'products.*.expiration_date.after' => 'La fecha de vencimiento debe ser posterior a hoy',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('products')) {
                $this->validateInventoryAvailability($validator);
            }
        });
    }

    /**
     * Validate that there is enough inventory for each product
     * IMPORTANTE: Convierte todas las cantidades a la unidad base antes de comparar
     */
    protected function validateInventoryAvailability($validator)
    {
        $originLocationId = $this->input('origin_location_id');

        foreach ($this->input('products', []) as $index => $productData) {
            $productId = $productData['product_id'] ?? null;
            $brandId = $productData['brand_id'] ?? null;
            $quantityDelivered = $productData['quantity_delivered'] ?? 0;
            $requestedUnit = $productData['unit'] ?? null;

            if (!$productId || !$brandId) {
                continue;
            }

            // Get all inventory records for this product/brand/location
            // NOTE: Expired products included — users need to dispose of them via outputs
            $inventoryRecords = \App\Models\Inventory::where('location_id', $originLocationId)
                ->where('product_id', $productId)
                ->where('brand_id', $brandId)
                ->where('quantity', '>', 0)
                ->get();

            if ($inventoryRecords->isEmpty()) {
                $validator->errors()->add(
                    "products.{$index}.quantity_delivered",
                    "No hay inventario disponible para este producto"
                );
                continue;
            }

            // Get product with packaging units to convert quantities
            $product = \App\Models\Product::with('packagingUnits')->find($productId);

            // Convert all inventory quantities to base unit and sum
            $availableInBaseUnit = 0;
            foreach ($inventoryRecords as $inventory) {
                $conversionFactor = 1;

                // Find the packaging unit that matches the inventory unit
                if ($product && $product->packagingUnits) {
                    $packagingUnit = $product->packagingUnits->first(function ($pu) use ($inventory) {
                        return strtolower($pu->name) === strtolower($inventory->unit);
                    });

                    if ($packagingUnit) {
                        // Convert to base unit: quantity * base_quantity
                        $conversionFactor = $packagingUnit->base_quantity;
                    }
                }

                $availableInBaseUnit += $inventory->quantity * $conversionFactor;
            }

            // Compare in base unit (the request already comes in base unit)
            if ($availableInBaseUnit < $quantityDelivered) {
                $baseUnit = $product && $product->packagingUnits->isNotEmpty()
                    ? $product->packagingUnits->first()->base_unit
                    : 'unidades';

                $validator->errors()->add(
                    "products.{$index}.quantity_delivered",
                    "No hay suficiente inventario disponible. Disponible: {$availableInBaseUnit} {$baseUnit}, solicitado: {$quantityDelivered} {$requestedUnit}"
                );
            }
        }
    }
}
