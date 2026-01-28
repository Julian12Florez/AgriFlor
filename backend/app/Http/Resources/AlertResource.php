<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'locationId' => $this->location_id,
            'productId' => $this->product_id,
            'severity' => $this->severity,
            'status' => $this->status,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'resolvedBy' => $this->resolved_by,
            'location' => new LocationResource($this->whenLoaded('location')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'resolver' => new UserResource($this->whenLoaded('resolver')),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
