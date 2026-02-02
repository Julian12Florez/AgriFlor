<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_code' => ['required', 'string', 'max:50', Rule::unique('workers', 'worker_code')],
            'full_name' => ['required', 'string', 'max:255'],
            'document_id' => ['required', 'string', 'max:50', Rule::unique('workers', 'document_id')],
            'hire_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_code.required' => 'El código del trabajador es requerido',
            'worker_code.unique' => 'El código del trabajador ya existe',
            'worker_code.max' => 'El código no puede exceder 50 caracteres',
            'full_name.required' => 'El nombre completo es requerido',
            'full_name.max' => 'El nombre no puede exceder 255 caracteres',
            'document_id.required' => 'El documento de identidad es requerido',
            'document_id.unique' => 'El documento de identidad ya existe',
            'document_id.max' => 'El documento no puede exceder 50 caracteres',
            'hire_date.required' => 'La fecha de ingreso es requerida',
            'hire_date.date' => 'La fecha de ingreso debe ser una fecha válida',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
