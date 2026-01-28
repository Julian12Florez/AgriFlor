<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductOutputResource extends JsonResource
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
            'outputNumber' => $this->output_number,
            'technicalOrderId' => $this->technical_order_id,
            'technicalOrder' => $this->when(
                $this->relationLoaded('technicalOrder') && $this->technicalOrder,
                function () {
                    return [
                        'id' => $this->technicalOrder->id,
                        'orderNumber' => $this->technicalOrder->order_number,
                        'orderDate' => $this->technicalOrder->order_date?->format('Y-m-d'),
                        'status' => $this->technicalOrder->status,
                    ];
                }
            ),
            'outputDate' => $this->output_date?->format('Y-m-d'),
            'originLocationId' => $this->origin_location_id,
            'originLocation' => $this->when(
                $this->relationLoaded('originLocation'),
                function () {
                    return [
                        'id' => $this->originLocation->id,
                        'name' => $this->originLocation->name,
                        'type' => $this->originLocation->type,
                        'municipality' => $this->originLocation->municipality,
                    ];
                }
            ),
            'destinationLocationId' => $this->destination_location_id,
            'destinationLocation' => $this->when(
                $this->relationLoaded('destinationLocation'),
                function () {
                    return [
                        'id' => $this->destinationLocation->id,
                        'name' => $this->destinationLocation->name,
                        'type' => $this->destinationLocation->type,
                        'municipality' => $this->destinationLocation->municipality,
                    ];
                }
            ),
            'status' => $this->status,
            'totalCost' => $this->when(
                $this->total_cost !== null,
                function () {
                    return (float) $this->total_cost;
                }
            ),
            'observations' => $this->observations,
            'outputTypeId' => $this->output_type_id,
            'outputType' => $this->when(
                $this->relationLoaded('outputType') && $this->outputType,
                function () {
                    return [
                        'id' => $this->outputType->id,
                        'name' => $this->outputType->name,
                        'code' => $this->outputType->code,
                        'requires_lots' => $this->outputType->requires_lots,
                    ];
                }
            ),
            'farmLots' => $this->when(
                $this->relationLoaded('farmLots'),
                function () {
                    return $this->farmLots->map(function ($lot) {
                        return [
                            'id' => $lot->id,
                            'name' => $lot->name,
                            'area' => $lot->area,
                            'area_unit' => $lot->area_unit,
                            'description' => $lot->description,
                            'location' => $this->when(
                                $lot->relationLoaded('location'),
                                function () use ($lot) {
                                    return [
                                        'id' => $lot->location->id,
                                        'name' => $lot->location->name,
                                    ];
                                }
                            ),
                        ];
                    });
                }
            ),
            'responsibleUser' => $this->responsible_user,
            'responsibleUserDetails' => $this->when(
                $this->relationLoaded('responsibleUser') && $this->responsibleUser,
                function () {
                    return [
                        'id' => $this->responsibleUser->id,
                        'name' => $this->responsibleUser->name,
                        'email' => $this->responsibleUser->email,
                    ];
                }
            ),
            'products' => $this->when(
                $this->relationLoaded('outputProducts'),
                function () {
                    return $this->outputProducts->map(function ($outputProduct) {
                        return [
                            'id' => $outputProduct->id,
                            'productId' => $outputProduct->product_id,
                            'productName' => $outputProduct->product?->name ?? null,
                            'product' => $this->when(
                                $outputProduct->relationLoaded('product'),
                                function () use ($outputProduct) {
                                    return [
                                        'id' => $outputProduct->product->id,
                                        'name' => $outputProduct->product->name,
                                        'category' => $outputProduct->product->category,
                                        'activeIngredient' => $outputProduct->product->active_ingredient,
                                    ];
                                }
                            ),
                            'brandId' => $outputProduct->brand_id,
                            'brandName' => $outputProduct->brand?->name ?? null,
                            'brand' => $this->when(
                                $outputProduct->relationLoaded('brand'),
                                function () use ($outputProduct) {
                                    return [
                                        'id' => $outputProduct->brand->id,
                                        'name' => $outputProduct->brand->name,
                                    ];
                                }
                            ),
                            'quantityRequested' => (float) $outputProduct->quantity_requested,
                            'quantityDelivered' => (float) $outputProduct->quantity_delivered,
                            'unit' => $outputProduct->unit,
                            'batchNumber' => $outputProduct->batch_number,
                            'expirationDate' => $outputProduct->expiration_date?->format('Y-m-d'),
                        ];
                    });
                }
            ),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
