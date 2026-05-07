<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'category' => $this->category,
            'categoryId' => $this->category_id,
            'category_id' => $this->category_id,
            'categoryData' => $this->whenLoaded('categoryRelation', fn() => $this->categoryRelation ? [
                'id' => $this->categoryRelation->id,
                'name' => $this->categoryRelation->name,
                'color' => $this->categoryRelation->color,
            ] : null),
            'description' => $this->description,
            'referenceYield' => $this->reference_yield,
            'reference_yield' => $this->reference_yield,
            'active' => $this->active,
            'overrideSobrepasoPct' => $this->override_sobrepaso_pct,
            'override_sobrepaso_pct' => $this->override_sobrepaso_pct,
            'overrideAltoPct' => $this->override_alto_pct,
            'override_alto_pct' => $this->override_alto_pct,
            'overrideMedioPct' => $this->override_medio_pct,
            'override_medio_pct' => $this->override_medio_pct,
            'overrideKFactor' => $this->override_k_factor,
            'override_k_factor' => $this->override_k_factor,
            'metricsConfigured' => $this->override_sobrepaso_pct !== null
                || $this->override_alto_pct !== null
                || $this->override_medio_pct !== null
                || $this->override_k_factor !== null,
            'effectiveThresholds' => $this->effectiveThresholds(),
            'createdAt' => $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
