<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicalOrderResource extends JsonResource
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
            'orderNumber' => $this->order_number,
            'scheduledDate' => $this->scheduled_date?->format('Y-m-d'),
            'status' => $this->status,
            'estimatedCost' => $this->estimated_cost,
            'observations' => $this->observations,
            'appliedAt' => $this->applied_at?->toISOString(),
            'appliedBy' => $this->when(
                $this->relationLoaded('applier'),
                fn() => $this->applier ? $this->applier->name : null
            ),
            'createdAt' => $this->created_at?->toISOString(),

            // Relationships
            'farms' => $this->when(
                $this->relationLoaded('farms'),
                fn() => $this->farms->map(function ($farm) {
                    return [
                        'id' => $farm->id,
                        'name' => $farm->name,
                        'type' => $farm->type,
                        'municipality' => $farm->municipality,
                        'status' => $farm->status,
                    ];
                })
            ),

            'products' => $this->when(
                $this->relationLoaded('technicalOrderProducts'),
                fn() => $this->technicalOrderProducts->map(function ($orderProduct) {
                    return [
                        'id' => $orderProduct->id,
                        'productId' => $orderProduct->product_id,
                        'productName' => $orderProduct->product?->name,
                        'brandId' => $orderProduct->brand_id,
                        'brandName' => $orderProduct->brand?->name,
                        'quantity' => $orderProduct->quantity,
                        'unit' => $orderProduct->unit,
                        'observations' => $orderProduct->observations,
                    ];
                })
            ),

            'recipe' => $this->when(
                $this->relationLoaded('recipe'),
                fn() => $this->recipe ? [
                    'id' => $this->recipe->id,
                    'name' => $this->recipe->name,
                    'description' => $this->recipe->description,
                    'category' => $this->recipe->category,
                    'applicationInstructions' => $this->recipe->application_instructions,
                    'safetyNotes' => $this->recipe->safety_notes,
                    'estimatedCost' => $this->recipe->estimated_cost,
                ] : null
            ),

            'agronomist' => $this->when(
                $this->relationLoaded('agronomist'),
                fn() => $this->agronomist ? [
                    'id' => $this->agronomist->id,
                    'name' => $this->agronomist->name,
                    'email' => $this->agronomist->email,
                ] : null
            ),
        ];
    }
}
