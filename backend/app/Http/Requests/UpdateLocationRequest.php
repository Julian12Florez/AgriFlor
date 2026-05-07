<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['warehouse', 'farm'])],
            'municipality' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'coordinates_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'coordinates_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'total_workers' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'type.in' => 'El tipo seleccionado no es válido',
            'municipality.max' => 'El municipio no puede exceder 255 caracteres',
            'address.max' => 'La dirección no puede exceder 255 caracteres',
            'responsible_user_id.uuid' => 'El ID del responsable no es válido',
            'responsible_user_id.exists' => 'El responsable seleccionado no existe',
            'coordinates_lat.numeric' => 'La latitud debe ser un número',
            'coordinates_lat.between' => 'La latitud debe estar entre -90 y 90',
            'coordinates_lng.numeric' => 'La longitud debe ser un número',
            'coordinates_lng.between' => 'La longitud debe estar entre -180 y 180',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
