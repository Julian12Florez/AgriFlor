<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Reception;

class StoreReceptionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reception_id' => ['required', 'uuid', 'exists:receptions,id'],
            'reception_date' => ['required', 'date'],
            'received_by' => ['required', 'uuid', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.brand_id' => ['required', 'uuid', 'exists:brands,id'],  // INC-002: Changed to required
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', Rule::in(['good', 'damaged', 'expired'])],
            'items.*.expiration_date' => ['nullable', 'date'],
            'items.*.observations' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'reception_id.required' => 'El ID de recepción es requerido',
            'reception_id.uuid' => 'El formato del ID de recepción no es válido',
            'reception_id.exists' => 'La recepción no existe',
            'reception_date.required' => 'La fecha de recepción es requerida',
            'reception_date.date' => 'La fecha de recepción debe ser una fecha válida',
            'received_by.required' => 'El responsable de recepción es requerido',
            'received_by.uuid' => 'El formato del ID del responsable no es válido',
            'received_by.exists' => 'El usuario responsable no existe',
            'items.required' => 'Debe incluir al menos un producto',
            'items.array' => 'Los productos deben ser un arreglo',
            'items.min' => 'Debe incluir al menos un producto',
            'items.*.product_id.required' => 'El ID del producto es requerido',
            'items.*.product_id.uuid' => 'El formato del ID del producto no es válido',
            'items.*.product_id.exists' => 'El producto no existe',
            'items.*.brand_id.required' => 'El ID de la marca es requerido',
            'items.*.brand_id.uuid' => 'El formato del ID de la marca no es válido',
            'items.*.brand_id.exists' => 'La marca no existe',
            'items.*.quantity_received.required' => 'La cantidad recibida es requerida',
            'items.*.quantity_received.numeric' => 'La cantidad recibida debe ser un número',
            'items.*.quantity_received.gt' => 'La cantidad recibida debe ser mayor a 0',
            'items.*.condition.required' => 'La condición del producto es requerida',
            'items.*.condition.in' => 'La condición debe ser: good, damaged o expired',
            'items.*.expiration_date.date' => 'La fecha de vencimiento debe ser una fecha válida',
            'attachments.array' => 'Los adjuntos deben ser un arreglo',
            'attachments.*.file' => 'El adjunto debe ser un archivo',
            'attachments.*.max' => 'El adjunto no puede exceder 10MB',
            'attachments.*.mimes' => 'El adjunto debe ser un archivo PDF, imagen o documento de Office',
        ];
    }

    /**
     * Configure the validator instance to add custom validation rules
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get reception ID from route parameter
            $receptionId = $this->route('reception');

            if (!$receptionId) {
                return;
            }

            // Load reception with its items
            $reception = Reception::with('receptionItems')->find($receptionId);

            if (!$reception) {
                return;
            }

            // Validate each item to prevent over-receiving
            foreach ($this->items ?? [] as $index => $itemData) {
                // Find the reception item for this product and brand (INC-001 fix)
                $receptionItem = $reception->receptionItems
                    ->where('product_id', $itemData['product_id'])
                    ->where('brand_id', $itemData['brand_id'] ?? null)
                    ->first();

                if (!$receptionItem) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        "El producto con ID {$itemData['product_id']} no pertenece a esta recepción"
                    );
                    continue;
                }

                // Calculate what would be the new total received
                $currentReceived = $receptionItem->quantity_received;
                $newQuantity = $itemData['quantity_received'];
                $newTotalReceived = $currentReceived + $newQuantity;
                $quantityOrdered = $receptionItem->quantity_expected;

                // Check if new total exceeds ordered quantity
                if ($newTotalReceived > $quantityOrdered) {
                    $excess = $newTotalReceived - $quantityOrdered;

                    $validator->errors()->add(
                        "items.{$index}.quantity_received",
                        sprintf(
                            'No puede recibir %s unidades. Ya se recibieron %s de %s ordenadas. Máximo permitido en este lote: %s unidades (exceso: %s).',
                            number_format($newQuantity, 2),
                            number_format($currentReceived, 2),
                            number_format($quantityOrdered, 2),
                            number_format($receptionItem->quantity_pending, 2),
                            number_format($excess, 2)
                        )
                    );
                }

                // Warning if receiving exactly the pending amount (informative, not error)
                if ($newQuantity == $receptionItem->quantity_pending && $receptionItem->quantity_pending > 0) {
                    // This is OK - receiving exactly what's pending will complete the item
                    // No error, just log for reference
                    \Log::info("Receiving full pending quantity", [
                        'product_id' => $itemData['product_id'],
                        'quantity' => $newQuantity,
                        'pending' => $receptionItem->quantity_pending,
                    ]);
                }
            }
        });
    }
}
