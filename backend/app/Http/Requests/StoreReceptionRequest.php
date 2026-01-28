<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reception_number' => ['nullable', 'string', 'max:255', 'unique:receptions,reception_number'],
            'source_id' => ['required', 'uuid'],
            'source_type' => ['required', Rule::in(['purchase', 'output'])],
            'origin_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'shipment_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reception_number.required' => 'El número de recepción es requerido',
            'reception_number.unique' => 'El número de recepción ya existe',
            'reception_number.max' => 'El número de recepción no puede exceder 255 caracteres',
            'source_id.required' => 'El ID de origen es requerido',
            'source_id.uuid' => 'El formato del ID de origen no es válido',
            'source_type.required' => 'El tipo de origen es requerido',
            'source_type.in' => 'El tipo de origen debe ser purchase o output',
            'origin_location_id.required' => 'La ubicación de origen es requerida',
            'origin_location_id.uuid' => 'El formato de la ubicación de origen no es válido',
            'origin_location_id.exists' => 'La ubicación de origen no existe',
            'destination_location_id.required' => 'La ubicación de destino es requerida',
            'destination_location_id.uuid' => 'El formato de la ubicación de destino no es válido',
            'destination_location_id.exists' => 'La ubicación de destino no existe',
            'shipment_date.date' => 'La fecha de envío debe ser una fecha válida',
        ];
    }
}
