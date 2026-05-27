<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'productCode' => $this->product_code,
            'product_code' => $this->product_code, // For compatibility
            'categoryId' => $this->category_id,
            'category_id' => $this->category_id, // For compatibility
            'category' => $this->when(
                $this->relationLoaded('category'),
                fn() => $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ] : null
            ),
            'categoryName' => $this->category?->name,
            'baseUnit' => $this->base_unit,
            'base_unit' => $this->base_unit, // For compatibility
            'unit' => $this->base_unit, // For compatibility
            'activeIngredient' => $this->active_ingredient,
            'active_ingredient' => $this->active_ingredient, // For compatibility
            'minStock' => $this->min_stock,
            'min_stock' => $this->min_stock, // For compatibility
            'status' => $this->status,
            'iva' => $this->iva,
            'description' => $this->description,
            'brandId' => $this->brand_id,
            'brand_id' => $this->brand_id, // For compatibility
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'packagingUnits' => $this->when(
                $this->relationLoaded('packagingUnits'),
                fn() => $this->packagingUnits->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'baseQuantity' => (float) $unit->base_quantity,
                        'base_quantity' => (float) $unit->base_quantity, // For compatibility
                        'baseUnit' => $unit->base_unit,
                        'base_unit' => $unit->base_unit, // For compatibility
                    ];
                })
            ),
            'packaging_units' => $this->when(
                $this->relationLoaded('packagingUnits'),
                fn() => $this->packagingUnits->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'baseQuantity' => (float) $unit->base_quantity,
                        'base_quantity' => (float) $unit->base_quantity,
                        'baseUnit' => $unit->base_unit,
                        'base_unit' => $unit->base_unit,
                    ];
                })
            ),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
