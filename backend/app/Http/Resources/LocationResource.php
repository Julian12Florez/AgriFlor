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
            // Como número, no como texto. La columna está casteada a `decimal:8`
            // (Location.php:43-44) y el cast decimal de Laravel serializa string
            // ("6.31450000"), mientras que la tabla de Ubicaciones solo pinta la
            // coordenada si `typeof lat === 'number'` (Locations.tsx:347) — con el
            // string decía "No definidas" aunque el dato estuviera guardado.
            // Se convierte aquí y no en el modelo porque Location es Auditable:
            // cambiarle el cast movería la forma de los valores en `audits` entre
            // los eventos viejos y los nuevos.
            'coordinates' => [
                'lat' => $this->coordinates_lat !== null ? (float) $this->coordinates_lat : null,
                'lng' => $this->coordinates_lng !== null ? (float) $this->coordinates_lng : null,
            ],
            'status' => $this->status,
            // Sin esto el número de trabajadores se guarda pero no vuelve: la ficha de
            // la finca se abre con el campo vacío, el usuario cree que no se guardó, y
            // al guardar otro cambio el formulario lo manda en null y lo BORRA. De ahí
            // salía el "la finca no tiene registrado el número de trabajadores" al
            // programar tareas (TaskScheduleController::store).
            'total_workers' => $this->total_workers,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
