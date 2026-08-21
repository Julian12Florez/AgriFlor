<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NUNCA expone `logo_base64`: son cientos de KB por fila y multiplicarlos por
 * un listado paginado convertiría `GET /companies` en una respuesta inutilizable.
 * En su lugar se informa `hasLogo`; la imagen se sirve por
 * `GET /companies/{id}/logo`, y el PDF la toma vía CompanyInfo::resolve().
 */
class CompanyResource extends JsonResource
{
    /**
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
            'legalRep' => $this->legal_rep,
            'taxRegime' => $this->tax_regime,
            'ciiu' => $this->ciiu,
            'template' => $this->template,
            'hasLogo' => !empty($this->logo_base64),
            'logoMime' => $this->logo_mime,
            'isDefault' => (bool) $this->is_default,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
