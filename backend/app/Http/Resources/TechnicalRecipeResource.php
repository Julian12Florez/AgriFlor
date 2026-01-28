<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicalRecipeResource extends JsonResource
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
            'description' => $this->description,
            'category' => $this->category,
            'applicationInstructions' => $this->application_instructions,
            'safetyNotes' => $this->safety_notes,
            'estimatedCost' => $this->estimated_cost,
            'usageCount' => $this->usage_count,
            'status' => $this->status,
            'products' => $this->whenLoaded('recipeProducts', function () {
                return $this->recipeProducts->map(function ($recipeProduct) {
                    return [
                        'id' => $recipeProduct->id,
                        'productId' => $recipeProduct->product_id,
                        'brandId' => $recipeProduct->brand_id,
                        'quantity' => $recipeProduct->quantity,
                        'unit' => $recipeProduct->unit,
                        'applicationRate' => $recipeProduct->application_rate,
                        'observations' => $recipeProduct->observations,
                        'product' => new ProductResource($recipeProduct->whenLoaded('product')),
                        'brand' => new BrandResource($recipeProduct->whenLoaded('brand')),
                    ];
                });
            }),
            'createdBy' => $this->whenLoaded('creator', function () {
                return $this->creator?->name;
            }),
            'lastUsed' => $this->last_used?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
