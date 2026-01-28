<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role, // Backward compatibility
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];

        // Include role data and permissions if relationship is loaded
        if ($this->relationLoaded('roleRelation') && $this->roleRelation) {
            $data['roleData'] = [
                'id' => $this->roleRelation->id,
                'name' => $this->roleRelation->name,
                'displayName' => $this->roleRelation->display_name,
                'description' => $this->roleRelation->description,
                'hasFullAccess' => $this->roleRelation->has_full_access,
                'excludedModules' => $this->roleRelation->excluded_modules ?? [],
            ];

            // Include permissions if loaded
            if ($this->roleRelation->relationLoaded('permissions')) {
                $data['permissions'] = $this->roleRelation->permissions->pluck('name')->toArray();
                $data['accessibleModules'] = $this->roleRelation->getAccessibleModules();
            }
        }

        return $data;
    }
}
