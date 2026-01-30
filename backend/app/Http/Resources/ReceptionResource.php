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
            // FUN-001 fix: Load source details without morphTo to avoid SQL errors
            'sourceDetails' => $this->getSourceDetailsDirectly(),

            // Reception Items (Expected items from source)
            'items' => $this->when(
                $this->relationLoaded('receptionItems'),
                fn() => $this->receptionItems->map(function ($item) {
                    // Use source_item_id for exact OutputProduct lookup (no more ->first() ambiguity)
                    $suggestedExpirationDate = $item->expiration_date;

                    if (!$suggestedExpirationDate && $this->source_type === 'output' && $this->origin_location_id) {
                        $outputProduct = $item->source_item_id
                            ? \App\Models\OutputProduct::find($item->source_item_id)
                            : \App\Models\OutputProduct::where('product_output_id', $this->source_id)
                                ->where('product_id', $item->product_id)
                                ->where('brand_id', $item->brand_id)
                                ->first();

                        $inventoryQuery = \App\Models\Inventory::where('product_id', $item->product_id)
                            ->where('brand_id', $item->brand_id)
                            ->where('location_id', $this->origin_location_id)
                            ->whereNotIn('status', ['expired'])
                            ->where('quantity', '>', 0);
                        if ($outputProduct?->batch_number) {
                            $inventoryQuery->where('batch_number', $outputProduct->batch_number);
                        }
                        $inventory = $inventoryQuery->orderBy('expiration_date', 'asc')->first();

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
                        'sourceItemId' => $item->source_item_id,
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
     * Get source details using direct queries instead of morphTo.
     * FUN-001 fix: Avoids morphTo SQL errors by querying models directly.
     */
    protected function getSourceDetailsDirectly(): ?array
    {
        if (!$this->source_id || !$this->source_type) {
            return null;
        }

        try {
            if ($this->source_type === 'purchase') {
                $purchase = \App\Models\Purchase::with('supplier:id,name,email,phone')
                    ->find($this->source_id);

                if (!$purchase) {
                    return null;
                }

                return [
                    'type' => 'purchase',
                    'orderNumber' => $purchase->order_number,
                    'purchaseDate' => $purchase->purchase_date?->format('Y-m-d'),
                    'expectedDelivery' => $purchase->expected_delivery?->format('Y-m-d'),
                    'status' => $purchase->status,
                    'total' => $purchase->total,
                    'supplier' => $purchase->supplier ? [
                        'id' => $purchase->supplier->id,
                        'name' => $purchase->supplier->name,
                        'email' => $purchase->supplier->email,
                        'phone' => $purchase->supplier->phone,
                    ] : null,
                ];
            }

            if ($this->source_type === 'output') {
                $output = \App\Models\ProductOutput::with('technicalOrder:id,order_number,scheduled_date')
                    ->find($this->source_id);

                if (!$output) {
                    return null;
                }

                return [
                    'type' => 'output',
                    'outputNumber' => $output->output_number,
                    'outputDate' => $output->output_date?->format('Y-m-d'),
                    'status' => $output->status,
                    'totalCost' => $output->total_cost,
                    'technicalOrder' => $output->technicalOrder ? [
                        'id' => $output->technicalOrder->id,
                        'orderNumber' => $output->technicalOrder->order_number,
                        'scheduledDate' => $output->technicalOrder->scheduled_date?->format('Y-m-d'),
                    ] : null,
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error loading source details for reception', [
                'reception_id' => $this->id,
                'source_type' => $this->source_type,
                'source_id' => $this->source_id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
