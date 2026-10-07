<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Cualquier perfil de la pantalla de Perfiles (antes era una lista fija).
            // El auditor no se asigna desde aquí: no existe para la gestión de usuarios.
            'role' => ['required', 'string', Rule::exists('roles', 'name')->whereNotIn('name', User::SECRET_ROLES)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es requerido',
            'email.unique' => 'Este email ya está registrado',
            'name.required' => 'El nombre es requerido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'role.required' => 'El rol es requerido',
            'role.exists' => 'El rol seleccionado no es válido',
        ];
    }
}
