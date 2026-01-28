<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionResource extends JsonResource
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
            'receptionNumber' => $this->reception_number,
            'sourceId' => $this->source_id,
            'sourceType' => $this->source_type,
            'shipmentDate' => $this->shipment_date?->format('Y-m-d'),
            'status' => $this->status,
            'totalExpected' => $this->total_expected,
            'totalReceived' => $this->total_received,
            'completionPercentage' => $this->completion_percentage,
            'observations' => $this->observations,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),

            // Origin Location
            'originLocation' => $this->when(
                $this->relationLoaded('originLocation'),
                fn() => $this->originLocation ? [
                    'id' => $this->originLocation->id,
                    'name' => $this->originLocation->name,
                    'type' => $this->originLocation->type,
                    'address' => $this->originLocation->address,
                ] : null
            ),

            // Destination Location
            'destinationLocation' => $this->when(
                $this->relationLoaded('destinationLocation'),
                fn() => $this->destinationLocation ? [
                    'id' => $this->destinationLocation->id,
                    'name' => $this->destinationLocation->name,
                    'type' => $this->destinationLocation->type,
                    'address' => $this->destinationLocation->address,
                ] : null
            ),

            // Responsible User
            'responsibleUser' => $this->when(
                $this->relationLoaded('responsibleUser'),
                fn() => $this->responsibleUser ? [
                    'id' => $this->responsibleUser->id,
                    'name' => $this->responsibleUser->name,
                    'email' => $this->responsibleUser->email,
                ] : null
            ),

            // Source Details (Purchase or ProductOutput)
            // Note: source relation removed to avoid morphTo issues
            // Use source_id and source_type directly if needed
            'sourceDetails' => null,

            // Reception Items (Expected items from source)
            'items' => $this->when(
                $this->relationLoaded('receptionItems'),
                fn() => $this->receptionItems->map(function ($item) {
                    // Get suggested expiration date from origin inventory for outputs
                    $suggestedExpirationDate = null;
                    if ($this->source_type === 'output' && $this->origin_location_id) {
                        $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                            ->where('brand_id', $item->brand_id)
                            ->where('location_id', $this->origin_location_id)
                            ->where('status', 'good')
                            ->where('quantity', '>', 0)
                            ->orderBy('expiration_date', 'asc') // FIFO: earliest expiration
                            ->first();

                        $suggestedExpirationDate = $inventory?->expiration_date;
                    }

                    return [
                        'id' => $item->id,
                        'productId' => $item->product_id,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'category' => $item->product->category,
                            'packagingUnits' => $item->product->packagingUnits->map(fn($pu) => [
                                'id' => $pu->id,
                                'name' => $pu->name,
                                'baseQuantity' => $pu->base_quantity,
                                'baseUnit' => $pu->base_unit,
                            ]),
                        ] : null,
                        'productName' => $item->product?->name ?? null,
                        'brandId' => $item->brand_id,
                        'brand' => $item->brand ? [
                            'id' => $item->brand->id,
                            'name' => $item->brand->name,
                        ] : null,
                        'brandName' => $item->brand?->name ?? null,
                        'quantityExpected' => $item->quantity_expected,
                        'quantityReceived' => $item->quantity_received,
                        'quantityPending' => $item->quantity_pending,
                        'unit' => $item->unit,
                        'expirationDate' => $item->expiration_date?->format('Y-m-d'),
                        'suggestedExpirationDate' => $suggestedExpirationDate?->format('Y-m-d'),
                        'condition' => $item->condition ?? null,
                        'observations' => $item->observations ?? null,
                    ];
                })
            ),

            // Reception Batches History
            'batches' => $this->when(
                $this->relationLoaded('receptionBatches'),
                fn() => ReceptionBatchResource::collection($this->receptionBatches)
            ),
        ];
    }

    /**
     * Get source details based on source type
     * NOTE: DISABLED - morphTo relationship causes SQL errors
     * Use source_id and source_type fields directly if needed
     */
    protected function getSourceDetails(): ?array
    {
        // Disabled to avoid morphTo SQL errors
        return null;

        /* ORIGINAL CODE - COMMENTED OUT
        if (!$this->source) {
            return null;
        }

        if ($this->source_type === 'purchase') {
            return [
                'type' => 'purchase',
                'orderNumber' => $this->source->order_number ?? null,
                'purchaseDate' => $this->source->purchase_date?->format('Y-m-d') ?? null,
                'expectedDelivery' => $this->source->expected_delivery?->format('Y-m-d') ?? null,
                'status' => $this->source->status ?? null,
                'total' => $this->source->total ?? null,
                'supplier' => $this->source->supplier ? [
                    'id' => $this->source->supplier->id,
                    'name' => $this->source->supplier->name,
                    'contactName' => $this->source->supplier->contact_name,
                    'email' => $this->source->supplier->email,
                    'phone' => $this->source->supplier->phone,
                ] : null,
            ];
        }

        if ($this->source_type === 'output') {
            return [
                'type' => 'output',
                'outputNumber' => $this->source->output_number ?? null,
                'outputDate' => $this->source->output_date?->format('Y-m-d') ?? null,
                'status' => $this->source->status ?? null,
                'totalCost' => $this->source->total_cost ?? null,
                'technicalOrder' => $this->source->technicalOrder ? [
                    'id' => $this->source->technicalOrder->id,
                    'orderNumber' => $this->source->technicalOrder->order_number,
                    'scheduledDate' => $this->source->technicalOrder->scheduled_date?->format('Y-m-d'),
                ] : null,
            ];
        }

        return null;
        */
    }
}
