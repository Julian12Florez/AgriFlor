<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'nit' => $this->nit,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'paymentTerms' => $this->payment_terms,
            'status' => $this->status,
            'contacts' => $this->whenLoaded('contacts', function () {
                return $this->contacts->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'position' => $contact->position,
                        'phone' => $contact->phone,
                        'email' => $contact->email,
                        'createdAt' => $contact->created_at?->toISOString(),
                    ];
                });
            }),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
