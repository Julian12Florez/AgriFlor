<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdjustmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
            'type' => $this->type,
            'reason_id' => $this->reason_id,
            'notes' => $this->notes,
            'product_id' => $this->product_id,
            'brand_id' => $this->brand_id,
            'unit' => $this->unit,
            'quantity_mode' => $this->quantity_mode,
            'quantity' => floatval($this->quantity),
            'quantity_base' => $this->quantity_base !== null ? floatval($this->quantity_base) : null,
            'origin_location_id' => $this->origin_location_id,
            'destination_location_id' => $this->destination_location_id,
            'batch_number' => $this->batch_number,
            'unit_price' => $this->unit_price !== null ? floatval($this->unit_price) : null,
            'movement_date' => $this->movement_date?->toDateString(),
            'status' => $this->status,
            'responsible_user' => $this->responsible_user,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),

            // Flat fields (dual naming) para consumo directo en el frontend
            'reason_name' => $this->whenLoaded('reason', function () {
                return $this->reason?->name;
            }),
            'product_name' => $this->whenLoaded('product', function () {
                return $this->product?->name;
            }),
            'product_code' => $this->whenLoaded('product', function () {
                return $this->product?->product_code;
            }),
            // Unidad de `quantity_base` (ver AdjustmentController::toBase /
            // InventoryService::baseUnitOf): el frontend la necesita para
            // rotular el DELTA REAL aplicado en modo absoluto sin adivinar —
            // `unit` es la de CAPTURA (p. ej. "Bulto") y puede no coincidir.
            'product_base_unit' => $this->whenLoaded('product', function () {
                return $this->product?->base_unit ?: 'unidades';
            }),
            'brand_name' => $this->whenLoaded('brand', function () {
                return $this->brand?->name;
            }),
            'origin_location_name' => $this->whenLoaded('originLocation', function () {
                return $this->originLocation?->name;
            }),
            'destination_location_name' => $this->whenLoaded('destinationLocation', function () {
                return $this->destinationLocation?->name;
            }),
            'requester_name' => $this->whenLoaded('requester', function () {
                return $this->requester?->name;
            }),
            'approver_name' => $this->whenLoaded('approver', function () {
                return $this->approver?->name;
            }),
        ];
    }
}
