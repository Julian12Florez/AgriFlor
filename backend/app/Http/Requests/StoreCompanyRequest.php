<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'max:20', 'unique:companies,nit'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'legal_rep' => ['nullable', 'string', 'max:150'],
            'tax_regime' => ['nullable', 'string', 'max:200'],
            'ciiu' => ['nullable', 'string', 'max:50'],
            'template' => ['sometimes', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es requerido',
            'name.max' => 'El nombre no puede exceder 150 caracteres',
            'nit.required' => 'El NIT es requerido',
            'nit.max' => 'El NIT no puede exceder 20 caracteres',
            'nit.unique' => 'Ya existe una empresa con este NIT',
            'address.max' => 'La dirección no puede exceder 200 caracteres',
            'city.max' => 'La ciudad no puede exceder 100 caracteres',
            'phone.max' => 'El teléfono no puede exceder 100 caracteres',
            'email.email' => 'El correo electrónico no es válido',
            'email.max' => 'El correo electrónico no puede exceder 150 caracteres',
            'legal_rep.max' => 'El representante legal no puede exceder 150 caracteres',
            'tax_regime.max' => 'El régimen tributario no puede exceder 200 caracteres',
            'ciiu.max' => 'El código CIIU no puede exceder 50 caracteres',
            'template.max' => 'El nombre de la plantilla no puede exceder 30 caracteres',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
