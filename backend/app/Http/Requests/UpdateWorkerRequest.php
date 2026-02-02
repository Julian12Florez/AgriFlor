<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workerId = $this->route('id');

        return [
            'worker_code' => ['sometimes', 'string', 'max:50', Rule::unique('workers', 'worker_code')->ignore($workerId)],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'document_id' => ['sometimes', 'string', 'max:50', Rule::unique('workers', 'document_id')->ignore($workerId)],
            'hire_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_code.unique' => 'El código del trabajador ya existe',
            'worker_code.max' => 'El código no puede exceder 50 caracteres',
            'full_name.max' => 'El nombre no puede exceder 255 caracteres',
            'document_id.unique' => 'El documento de identidad ya existe',
            'document_id.max' => 'El documento no puede exceder 50 caracteres',
            'hire_date.date' => 'La fecha de ingreso debe ser una fecha válida',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
