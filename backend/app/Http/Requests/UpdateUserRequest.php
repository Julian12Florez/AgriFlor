<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            // Cualquier perfil de la pantalla de Perfiles (antes era una lista fija).
            // El auditor no se asigna desde aquí: no existe para la gestión de usuarios.
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')->whereNotIn('name', User::SECRET_ROLES)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este email ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'role.exists' => 'El rol seleccionado no es válido',
            'status.in' => 'El estado seleccionado no es válido',
        ];
    }
}
