<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Use stored values from database (per-product IVA calculation)
        $subtotal = (float) ($this->subtotal ?? 0);
        $tax = (float) ($this->tax ?? 0);
        $total = (float) ($this->total ?? 0);

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'purchaseDate' => $this->purchase_date?->format('Y-m-d'),
            'expectedDelivery' => $this->expected_delivery?->format('Y-m-d'),
            'status' => $this->status,
            'subtotal' => (float) number_format($subtotal, 2, '.', ''),
            'tax' => (float) number_format($tax, 2, '.', ''),
            'total' => (float) number_format($total, 2, '.', ''),
            'observations' => $this->observations,
            'receivedAt' => $this->received_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),

            // Direct fields for easier access
            'supplierId' => $this->supplier_id,
            'supplierName' => $this->supplier?->name ?? null,
            'originLocationId' => $this->origin_location_id,
            'originLocationName' => $this->originLocation?->name ?? null,
            'destinationLocationId' => $this->destination_location_id,
            'destinationLocationName' => $this->destinationLocation?->name ?? null,
            'createdBy' => $this->creator?->name ?? null,

            // Relationships
            'supplier' => $this->when(
                $this->relationLoaded('supplier'),
                fn() => $this->supplier ? [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                    'nit' => $this->supplier->nit,
                    'address' => $this->supplier->address,
                    'city' => $this->supplier->city,
                    'phone' => $this->supplier->phone,
                    'email' => $this->supplier->email,
                    'paymentTerms' => $this->supplier->payment_terms,
                    'status' => $this->supplier->status,
                    'contacts' => $this->supplier->relationLoaded('contacts')
                        ? $this->supplier->contacts->map(fn($c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'position' => $c->position,
                            'phone' => $c->phone,
                            'email' => $c->email,
                        ])->values()
                        : [],
                ] : null
            ),

            'destinationLocation' => $this->when(
                $this->relationLoaded('destinationLocation'),
                fn() => $this->destinationLocation ? [
                    'id' => $this->destinationLocation->id,
                    'name' => $this->destinationLocation->name,
                    'type' => $this->destinationLocation->type,
                    'municipality' => $this->destinationLocation->municipality,
                    'status' => $this->destinationLocation->status,
                ] : null
            ),

            'items' => $this->when(
                $this->relationLoaded('purchaseItems'),
                fn() => $this->purchaseItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'productId' => $item->product_id,
                        'productName' => $item->product?->name,
                        'brandId' => $item->brand_id,
                        'brandName' => $item->brand?->name,
                        'packagingUnitId' => $item->packaging_unit_id,
                        'packagingUnitName' => $item->packagingUnit?->name,
                        'baseQuantityPerUnit' => $item->packagingUnit?->base_quantity,
                        'quantity' => (float) $item->quantity,
                        'quantityInBaseUnits' => (float) $item->quantity_in_base_units,
                        'unit' => $item->packagingUnit?->base_unit ?? $item->product?->base_unit ?? 'kg',
                        'unitPrice' => (float) $item->unit_price,
                        'subtotal' => (float) $item->subtotal,
                        'ivaPercentage' => (int) ($item->iva_percentage ?? 0),
                        'taxAmount' => (float) ($item->tax_amount ?? 0),
                        'total' => (float) ($item->total ?? $item->subtotal ?? 0),
                        'expirationDate' => $item->expiration_date?->format('Y-m-d'),
                    ];
                })
            ),

            'attachments' => $this->when(
                $this->relationLoaded('attachments'),
                fn() => $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'fileName' => $attachment->file_name,
                        'filePath' => $attachment->file_path,
                        'fileType' => $attachment->file_type,
                        'fileSize' => $attachment->file_size,
                        'uploadedBy' => $attachment->uploader?->name,
                        'createdAt' => $attachment->created_at?->toISOString(),
                    ];
                })
            ),

            'creator' => $this->when(
                $this->relationLoaded('creator'),
                fn() => $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ] : null
            ),

            'receiver' => $this->when(
                $this->relationLoaded('receiver'),
                fn() => $this->receiver ? [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                    'email' => $this->receiver->email,
                ] : null
            ),
        ];
    }
}
