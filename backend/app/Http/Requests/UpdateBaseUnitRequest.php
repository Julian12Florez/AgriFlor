<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBaseUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseUnitId = $this->route('base_unit');

        return [
            'name' => ['sometimes', 'string', 'max:50', Rule::unique('base_units', 'name')->ignore($baseUnitId)],
            'symbol' => ['sometimes', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Esta unidad base ya existe',
        ];
    }
}
