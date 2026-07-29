<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreAdjustmentRequest extends FormRequest
{
    /**
     * El cliente pidió que CUALQUIER rol autenticado pueda crear solicitudes de ajuste.
     * El aislamiento por ubicación se aplica en la lectura (AdjustmentController::index),
     * no en la creación.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:entry,exit,transfer'],
            'reason_id' => ['required', 'exists:adjustment_reasons,id'],
            'notes' => ['nullable', 'string'],
            'product_id' => ['required', 'exists:products,id'],
            'brand_id' => ['required', 'exists:brands,id'],
            'unit' => ['required', 'string'],
            'quantity_mode' => ['required', 'in:delta,absolute'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'origin_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'batch_number' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'movement_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de ajuste es requerido.',
            'type.in' => 'El tipo de ajuste debe ser entrada, salida o traslado.',
            'reason_id.required' => 'El motivo es requerido.',
            'reason_id.exists' => 'El motivo seleccionado no existe.',
            'notes.string' => 'Las notas deben ser texto.',
            'product_id.required' => 'El producto es requerido.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'brand_id.required' => 'La marca es requerida.',
            'brand_id.exists' => 'La marca seleccionada no existe.',
            'unit.required' => 'La unidad es requerida.',
            'unit.string' => 'La unidad no es válida.',
            'quantity_mode.required' => 'El modo de cantidad es requerido.',
            'quantity_mode.in' => 'El modo de cantidad debe ser delta o absoluto.',
            'quantity.required' => 'La cantidad es requerida.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad debe ser mayor a 0.',
            'origin_location_id.uuid' => 'El formato de la ubicación de origen no es válido.',
            'origin_location_id.exists' => 'La ubicación de origen seleccionada no existe.',
            'destination_location_id.uuid' => 'El formato de la ubicación de destino no es válido.',
            'destination_location_id.exists' => 'La ubicación de destino seleccionada no existe.',
            'batch_number.string' => 'El número de lote no es válido.',
            'unit_price.numeric' => 'El precio unitario debe ser un número.',
            'unit_price.min' => 'El precio unitario no puede ser negativo.',
            'movement_date.required' => 'La fecha de movimiento es requerida.',
            'movement_date.date' => 'La fecha de movimiento no es válida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateLocationsForType($validator);
            $this->validateQuantityMode($validator);
        });
    }

    /**
     * Cada tipo de ajuste exige ubicaciones distintas:
     * - entry: destino + precio unitario (el ingreso valora el lote).
     * - exit: origen.
     * - transfer: origen y destino, y deben ser diferentes.
     */
    protected function validateLocationsForType(Validator $validator): void
    {
        $type = $this->input('type');

        if ($type === 'entry') {
            $this->requireField($validator, 'destination_location_id', 'La ubicación de destino es requerida para una entrada.');
            if ($this->input('unit_price') === null) {
                $validator->errors()->add('unit_price', 'El precio unitario es requerido para una entrada.');
            }
        }

        if ($type === 'exit') {
            $this->requireField($validator, 'origin_location_id', 'La ubicación de origen es requerida para una salida.');
        }

        if ($type === 'transfer') {
            $this->requireField($validator, 'origin_location_id', 'La ubicación de origen es requerida para un traslado.');
            $this->requireField($validator, 'destination_location_id', 'La ubicación de destino es requerida para un traslado.');

            if ($this->filled('origin_location_id') && $this->filled('destination_location_id')
                && $this->input('origin_location_id') === $this->input('destination_location_id')) {
                $validator->errors()->add(
                    'destination_location_id',
                    'La ubicación de destino debe ser diferente a la ubicación de origen en un traslado.'
                );
            }
        }
    }

    /**
     * El modo absoluto exige un lote identificable y solo aplica a entrada/salida:
     * un traslado absoluto no tiene sentido porque mueve cantidad, no fija un saldo.
     */
    protected function validateQuantityMode(Validator $validator): void
    {
        if ($this->input('quantity_mode') !== 'absolute') {
            return;
        }

        $this->requireField($validator, 'batch_number', 'El número de lote es requerido cuando el modo de cantidad es absoluto.');

        if (!in_array($this->input('type'), ['entry', 'exit'], true)) {
            $validator->errors()->add('quantity_mode', 'El modo de cantidad absoluto solo aplica a entradas o salidas.');
        }
    }

    protected function requireField(Validator $validator, string $field, string $message): void
    {
        if (!$this->filled($field)) {
            $validator->errors()->add($field, $message);
        }
    }
}
