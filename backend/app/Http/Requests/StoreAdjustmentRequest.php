<?php

namespace App\Http\Requests;

use App\Models\AdjustmentReason;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    /**
     * El cliente pidió que CUALQUIER rol autenticado pueda crear solicitudes de ajuste.
     * El aislamiento por ubicación se aplica en la lectura (AdjustmentController::index)
     * y, para roles restringidos, también al crear (AdjustmentController::store valida
     * que las ubicaciones enviadas sean administradas por el usuario) — pero esa es una
     * regla de negocio sobre los DATOS del payload, no de autorización de la petición en
     * sí, así que se resuelve en el controlador (que tiene acceso a $request->user() con
     * el modelo completo) en vez de aquí.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:entry,exit,transfer'],
            'reason_id' => ['required', 'uuid', 'exists:adjustment_reasons,id'],
            'notes' => ['nullable', 'string'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'unit' => ['required', 'string', 'max:255'],
            'quantity_mode' => ['required', 'in:delta,absolute'],
            // El mínimo efectivo depende del modo (ver validateQuantityForMode):
            // en modo absoluto 0 es un valor legítimo ("el conteo físico dio cero").
            'quantity' => ['required', 'numeric', 'min:0'],
            'origin_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'batch_number' => ['nullable', 'string', 'max:255'],
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
            'reason_id.uuid' => 'El formato del motivo no es válido.',
            'reason_id.exists' => 'El motivo seleccionado no existe.',
            'notes.string' => 'Las notas deben ser texto.',
            'product_id.required' => 'El producto es requerido.',
            'product_id.uuid' => 'El formato del producto no es válido.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'brand_id.required' => 'La marca es requerida.',
            'brand_id.uuid' => 'El formato de la marca no es válido.',
            'brand_id.exists' => 'La marca seleccionada no existe.',
            'unit.required' => 'La unidad es requerida.',
            'unit.string' => 'La unidad no es válida.',
            'unit.max' => 'La unidad no puede exceder 255 caracteres.',
            'quantity_mode.required' => 'El modo de cantidad es requerido.',
            'quantity_mode.in' => 'El modo de cantidad debe ser delta o absoluto.',
            'quantity.required' => 'La cantidad es requerida.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad no puede ser negativa.',
            'origin_location_id.uuid' => 'El formato de la ubicación de origen no es válido.',
            'origin_location_id.exists' => 'La ubicación de origen seleccionada no existe.',
            'destination_location_id.uuid' => 'El formato de la ubicación de destino no es válido.',
            'destination_location_id.exists' => 'La ubicación de destino seleccionada no existe.',
            'batch_number.string' => 'El número de lote no es válido.',
            'batch_number.max' => 'El número de lote no puede exceder 255 caracteres.',
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
            $this->validateQuantityForMode($validator);
            $this->validateUnitBelongsToProduct($validator);
            $this->validateReasonDirection($validator);
        });
    }

    /**
     * Cada tipo de ajuste exige ubicaciones distintas, y SOLO esas: la ubicación
     * que no aplica al tipo se rechaza en vez de ignorarse en silencio, porque
     * una fila con una ubicación "sobrante" (p. ej. una entrada con origen)
     * contamina el scope por ubicación de index()/show() (que mira ambas
     * columnas) y dejaría a la futura aprobación con un estado imposible de
     * interpretar (¿de dónde sale una entrada que también tiene origen?).
     * - entry: solo destino (+ precio unitario, valora el lote que ingresa).
     * - exit: solo origen.
     * - transfer: origen y destino, y deben ser diferentes.
     */
    protected function validateLocationsForType(Validator $validator): void
    {
        $type = $this->input('type');

        if ($type === 'entry') {
            $this->requireField($validator, 'destination_location_id', 'La ubicación de destino es requerida para una entrada.');
            $this->rejectField($validator, 'origin_location_id', 'Una entrada no debe tener ubicación de origen; solo destino.');

            if ($this->input('unit_price') === null) {
                $validator->errors()->add('unit_price', 'El precio unitario es requerido para una entrada.');
            }
        }

        if ($type === 'exit') {
            $this->requireField($validator, 'origin_location_id', 'La ubicación de origen es requerida para una salida.');
            $this->rejectField($validator, 'destination_location_id', 'Una salida no debe tener ubicación de destino; solo origen.');
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

    /**
     * En modo `delta` la cantidad es lo que se mueve, así que un 0 no ajustaría
     * nada. En modo `absoluto` la cantidad es el saldo QUE DEBE QUEDAR en el
     * lote, y 0 es un caso legítimo y frecuente: el conteo físico encontró el
     * lote vacío y hay que darlo de baja completo (approve() lo resuelve como
     * un delta igual a todo lo que había).
     */
    protected function validateQuantityForMode(Validator $validator): void
    {
        $quantity = $this->input('quantity');

        if (!is_numeric($quantity)) {
            // Ya reportado por quantity.required/numeric.
            return;
        }

        if ($this->input('quantity_mode') !== 'absolute' && (float) $quantity < 0.01) {
            $validator->errors()->add('quantity', 'La cantidad debe ser mayor a 0.');
        }
    }

    /**
     * `unit` es load-bearing para la aprobación: InventoryService::toBaseUnit()
     * (usada por InventoryService::addStock cuando el ajuste se apruebe) busca
     * `unit` como el NOMBRE de una PackagingUnit asociada al producto; si no
     * encuentra coincidencia, asume silenciosamente que la cantidad YA está en
     * la unidad base del producto y la aplica tal cual — una unidad inventada
     * (p. ej. "banana") pasaría de largo esa conversión y corrompería el stock
     * al aprobar. Por eso aquí se exige explícitamente que `unit` sea, o bien
     * la unidad base efectiva del producto (`products.base_unit`, con el mismo
     * fallback 'unidades' que usa InventoryService::baseUnitOf cuando es NULL),
     * o bien el nombre (case-insensitive, igual que
     * InventoryService::findPackagingUnit) de una de sus PackagingUnit
     * asociadas — es decir, exactamente el universo de valores que
     * InventoryService sabe interpretar para este producto.
     */
    protected function validateUnitBelongsToProduct(Validator $validator): void
    {
        $productId = $this->input('product_id');
        $unit = $this->input('unit');

        if (!is_string($productId) || !is_string($unit) || $unit === '') {
            // Ya reportado por product_id.uuid/exists o unit.required/string.
            return;
        }

        $product = Product::find($productId);

        if (!$product) {
            // Ya reportado por product_id.exists.
            return;
        }

        $effectiveBaseUnit = $product->base_unit ?: 'unidades';

        if (strcasecmp($effectiveBaseUnit, $unit) === 0) {
            return;
        }

        $isPackagingUnit = $product->packagingUnits()
            ->whereRaw('LOWER(name) = ?', [strtolower($unit)])
            ->exists();

        if (!$isPackagingUnit) {
            $validator->errors()->add(
                'unit',
                'La unidad indicada no corresponde a la unidad base ni a una presentación registrada del producto.'
            );
        }
    }

    /**
     * El motivo (adjustment_reasons.direction) puede restringirse a un tipo de
     * movimiento ('entry'|'exit'|'transfer') o valer 'any' para cualquiera.
     * Sin este chequeo, un motivo pensado exclusivamente para salidas (p. ej.
     * "merma_dano") podía aplicarse a una entrada, lo que no tiene sentido de
     * negocio y confundiría cualquier reporte que agrupe por motivo.
     */
    protected function validateReasonDirection(Validator $validator): void
    {
        $reasonId = $this->input('reason_id');
        $type = $this->input('type');

        if (!is_string($reasonId) || !is_string($type)) {
            return;
        }

        $reason = AdjustmentReason::find($reasonId);

        if (!$reason) {
            // Ya reportado por reason_id.exists.
            return;
        }

        if ($reason->direction !== 'any' && $reason->direction !== $type) {
            $validator->errors()->add(
                'reason_id',
                'El motivo seleccionado no aplica para el tipo de ajuste indicado.'
            );
        }
    }

    protected function requireField(Validator $validator, string $field, string $message): void
    {
        if (!$this->filled($field)) {
            $validator->errors()->add($field, $message);
        }
    }

    protected function rejectField(Validator $validator, string $field, string $message): void
    {
        if ($this->filled($field)) {
            $validator->errors()->add($field, $message);
        }
    }
}
