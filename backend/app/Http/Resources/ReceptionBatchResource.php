<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionBatchResource extends JsonResource
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
            'receptionId' => $this->reception_id,
            'batchNumber' => $this->batch_number,
            'batch_number' => $this->batch_number, // Para compatibilidad con frontend
            'receptionDate' => $this->reception_date?->format('Y-m-d H:i:s'),
            'reception_date' => $this->reception_date?->format('Y-m-d H:i:s'), // Para compatibilidad con frontend
            'observations' => $this->observations,
            'createdAt' => $this->created_at?->toISOString(),

            // Received By User - Return name directly for display
            'receivedBy' => $this->when(
                $this->relationLoaded('receiver'),
                fn() => $this->receiver?->name ?? 'N/A'
            ),
            'received_by' => $this->when(
                $this->relationLoaded('receiver'),
                fn() => $this->receiver?->name ?? 'N/A'
            ),

            // Batch Items (Products received in this batch)
            'items' => $this->when(
                $this->relationLoaded('batchItems'),
                fn() => $this->batchItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'receptionItemId' => $item->reception_item_id,
                        'productId' => $item->product_id,
                        'productName' => $item->product?->name ?? null,
                        'quantityReceived' => $item->quantity_received,
                        'condition' => $item->condition,
                        'expirationDate' => $item->expiration_date?->format('Y-m-d'),
                        'observations' => $item->observations,
                        'createdAt' => $item->created_at?->toISOString(),
                    ];
                })
            ),
            'batch_items' => $this->when(
                $this->relationLoaded('batchItems'),
                fn() => $this->batchItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'receptionItemId' => $item->reception_item_id,
                        'productId' => $item->product_id,
                        'productName' => $item->product?->name ?? null,
                        'quantityReceived' => $item->quantity_received,
                        'condition' => $item->condition,
                        'expirationDate' => $item->expiration_date?->format('Y-m-d'),
                        'observations' => $item->observations,
                        'createdAt' => $item->created_at?->toISOString(),
                    ];
                })
            ),

            // Attachments
            'attachments' => $this->when(
                $this->relationLoaded('attachments'),
                fn() => $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'fileName' => $attachment->file_name,
                        'filePath' => $attachment->file_path,
                        'fileType' => $attachment->file_type,
                        'fileSize' => $attachment->file_size,
                        'uploadedAt' => $attachment->uploaded_at?->toISOString(),
                    ];
                })
            ),
        ];
    }
}
