<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate packaging unit details if product is loaded
        $packagingUnitName = null;
        $baseQuantity = null;
        $baseUnit = null;
        $totalQuantityInBaseUnit = null;

        if ($this->relationLoaded('product') && $this->product && $this->unit) {
            // Load packaging units if not already loaded
            if (!$this->product->relationLoaded('packagingUnits')) {
                $this->product->load('packagingUnits');
            }

            // Find matching packaging unit by name OR base_unit
            $packagingUnit = $this->product->packagingUnits?->first(function ($pu) {
                $unitLower = strtolower($this->unit);
                return strtolower($pu->name) === $unitLower ||
                       strtolower($pu->base_unit) === $unitLower;
            });

            if ($packagingUnit) {
                // Check if this unit matches the packaging unit name (e.g., "Saco")
                // or if it's the base unit (e.g., "kg")
                $isPackagingUnit = strtolower($packagingUnit->name) === strtolower($this->unit);

                if ($isPackagingUnit) {
                    // Unit is the packaging unit (e.g., "Saco")
                    $packagingUnitName = $packagingUnit->name;
                    $baseQuantity = floatval($packagingUnit->base_quantity);
                    $baseUnit = $packagingUnit->base_unit;
                    $totalQuantityInBaseUnit = floatval($this->quantity) * $baseQuantity;
                } else {
                    // Unit is already the base unit (e.g., "kg")
                    $packagingUnitName = $this->unit;
                    $baseQuantity = 1;
                    $baseUnit = $this->unit;
                    $totalQuantityInBaseUnit = floatval($this->quantity);
                }
            } else {
                // If no packaging unit found, unit is already base unit
                $packagingUnitName = $this->unit;
                $baseQuantity = 1;
                $baseUnit = $this->unit;
                $totalQuantityInBaseUnit = floatval($this->quantity);
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'product_id' => $this->product_id,
            'brand_id' => $this->brand_id,
            'location_id' => $this->location_id,
            'quantity' => floatval($this->quantity ?? 0),
            'unit' => $this->unit,
            'packaging_unit_name' => $packagingUnitName,
            'base_quantity' => $baseQuantity,
            'base_unit' => $baseUnit,
            'total_quantity_in_base_unit' => $totalQuantityInBaseUnit,
            'expiration_date' => $this->expiration_date,
            'unit_price' => floatval($this->unit_price ?? 0),
            'total_price' => floatval($this->total_price ?? 0),
            'responsible_user' => $this->responsible_user,
            'related_document_id' => $this->related_document_id,
            'related_document_type' => $this->related_document_type,
            'observations' => $this->observations,

            // Flat fields for easy access in frontend
            'product_name' => $this->whenLoaded('product', function() {
                return $this->product?->name;
            }),
            'product_code' => $this->whenLoaded('product', function() {
                return $this->product?->product_code;
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
            'responsible_user_name' => $this->whenLoaded('responsibleUser', function() {
                return $this->responsibleUser?->name;
            }),

            // Origin and destination for traceability
            'origin_location_id' => $this->getOriginLocationId(),
            'origin_location_name' => $this->getOriginLocationName(),
            'destination_location_id' => $this->getDestinationLocationId(),
            'destination_location_name' => $this->getDestinationLocationName(),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Format related document based on type
     */
    private function formatRelatedDocument()
    {
        if (!$this->relatedDocument) {
            return null;
        }

        return [
            'id' => $this->relatedDocument->id,
            'type' => $this->related_document_type,
            'number' => $this->relatedDocument->number ?? $this->relatedDocument->code ?? null,
            'date' => $this->relatedDocument->date ?? $this->relatedDocument->created_at ?? null,
        ];
    }

    /**
     * Get origin location ID based on movement type
     */
    private function getOriginLocationId(): ?string
    {
        if ($this->type === 'exit') {
            // For EXIT: origin is the current location
            return $this->location_id;
        } elseif ($this->type === 'entry') {
            // For ENTRY: origin comes from reception's origin_location
            if ($this->related_document_type === 'App\\Models\\Reception') {
                $reception = \App\Models\Reception::find($this->related_document_id);
                return $reception?->origin_location_id;
            }
        }
        return null;
    }

    /**
     * Get origin location name based on movement type
     */
    private function getOriginLocationName(): ?string
    {
        if ($this->type === 'exit') {
            // For EXIT: origin is the current location
            return $this->location?->name;
        } elseif ($this->type === 'entry') {
            // For ENTRY: origin comes from reception's origin_location
            if ($this->related_document_type === 'App\\Models\\Reception') {
                $reception = \App\Models\Reception::with('originLocation')->find($this->related_document_id);
                return $reception?->originLocation?->name;
            }
        }
        return null;
    }

    /**
     * Get destination location ID based on movement type
     */
    private function getDestinationLocationId(): ?string
    {
        if ($this->type === 'entry') {
            // For ENTRY: destination is the current location
            return $this->location_id;
        } elseif ($this->type === 'exit') {
            // For EXIT: destination comes from reception's destination_location
            if ($this->related_document_type === 'App\\Models\\Reception') {
                $reception = \App\Models\Reception::find($this->related_document_id);
                return $reception?->destination_location_id;
            }
        }
        return null;
    }

    /**
     * Get destination location name based on movement type
     */
    private function getDestinationLocationName(): ?string
    {
        if ($this->type === 'entry') {
            // For ENTRY: destination is the current location
            return $this->location?->name;
        } elseif ($this->type === 'exit') {
            // For EXIT: destination comes from reception's destination_location
            if ($this->related_document_type === 'App\\Models\\Reception') {
                $reception = \App\Models\Reception::with('destinationLocation')->find($this->related_document_id);
                return $reception?->destinationLocation?->name;
            }
        }
        return null;
    }
}
