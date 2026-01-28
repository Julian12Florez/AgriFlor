<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
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
            'type' => $this->type,
            'municipality' => $this->municipality,
            'address' => $this->address,
            'responsible_user_id' => $this->responsible_user_id,
            'responsible_user' => $this->when(
                $this->relationLoaded('responsibleUser') && $this->responsibleUser,
                fn() => [
                    'id' => $this->responsibleUser->id,
                    'name' => $this->responsibleUser->name,
                    'email' => $this->responsibleUser->email,
                ]
            ),
            'coordinates' => [
                'lat' => $this->coordinates_lat,
                'lng' => $this->coordinates_lng,
            ],
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
