<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Find packaging unit to get base quantity and base unit
        $baseQuantity = 1; // Default
        $baseUnit = $this->unit; // Default to current unit
        $totalBaseQuantity = floatval($this->quantity ?? 0); // Default to current quantity

        if ($this->relationLoaded('product') && $this->product && $this->product->relationLoaded('packagingUnits')) {
            $packagingUnit = $this->product->packagingUnits->first(function ($pu) {
                return strtolower($pu->name) === strtolower($this->unit);
            });

            if ($packagingUnit) {
                $baseQuantity = floatval($packagingUnit->base_quantity);
                $baseUnit = $packagingUnit->base_unit;
                $totalBaseQuantity = floatval($this->quantity ?? 0) * $baseQuantity;
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'brand_id' => $this->brand_id,
            'location_id' => $this->location_id,
            'batch_number' => $this->batch_number,
            'quantity' => floatval($this->quantity ?? 0),
            'unit' => $this->unit,
            'base_quantity' => $baseQuantity,
            'base_unit' => $baseUnit,
            'total_base_quantity' => $totalBaseQuantity,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'unit_price' => floatval($this->unit_price ?? 0),
            'total_value' => floatval($this->total_value ?? 0),
            'status' => $this->calculateStatus(),

            // Flat fields for easy access in frontend
            'product_name' => $this->whenLoaded('product', function() {
                return $this->product?->name;
            }),
            'product_code' => $this->whenLoaded('product', function() {
                return $this->product?->product_code;
            }),
            'category' => $this->whenLoaded('product', function() {
                return $this->product?->category;
            }),
            'brand_name' => $this->whenLoaded('brand', function() {
                return $this->brand?->name;
            }),
            'location_name' => $this->whenLoaded('location', function() {
                return $this->location?->name;
            }),
            'location_type' => $this->whenLoaded('location', function() {
                return $this->location?->type;
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Calculate inventory status based on quantity and expiration date
     */
    private function calculateStatus(): string
    {
        // Check if expired
        if ($this->expiration_date && $this->expiration_date->isPast()) {
            return 'expired';
        }

        // Check if near expiry (30 days or less)
        if ($this->expiration_date && $this->expiration_date->diffInDays(Carbon::now()) <= 30) {
            return 'near_expiry';
        }

        // Check if low stock (compare with product min_stock if available)
        if ($this->product && $this->product->min_stock) {
            if ($this->quantity <= $this->product->min_stock) {
                return 'low';
            }
        }

        return 'good';
    }
}
