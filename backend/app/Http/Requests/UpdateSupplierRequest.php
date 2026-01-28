<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'nit' => ['sometimes', 'string', 'max:50', Rule::unique('suppliers', 'nit')->ignore($supplierId)],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'nit.max' => 'El NIT no puede exceder 50 caracteres',
            'nit.unique' => 'Ya existe un proveedor con este NIT',
            'address.max' => 'La dirección no puede exceder 500 caracteres',
            'city.max' => 'La ciudad no puede exceder 100 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'email.email' => 'El email debe ser una dirección de correo válida',
            'email.max' => 'El email no puede exceder 255 caracteres',
            'payment_terms.max' => 'Los términos de pago no pueden exceder 500 caracteres',
            'status.in' => 'El estado seleccionado no es válido',
            'contacts.array' => 'Los contactos deben ser un arreglo',
            'contacts.*.name.required_with' => 'El nombre del contacto es requerido',
            'contacts.*.name.max' => 'El nombre del contacto no puede exceder 255 caracteres',
            'contacts.*.position.max' => 'El cargo no puede exceder 100 caracteres',
            'contacts.*.phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'contacts.*.email.email' => 'El email del contacto debe ser válido',
            'contacts.*.email.max' => 'El email del contacto no puede exceder 255 caracteres',
        ];
    }
}
