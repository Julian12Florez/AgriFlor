<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectAdjustmentRequest extends FormRequest
{
    /**
     * La ruta que usa este FormRequest ya está protegida por el middleware
     * `role:admin` (ver routes/api.php), que es el único mecanismo de
     * autorización para rechazar: no se reutiliza aquí el guard de
     * visibilidad `AdjustmentController::canAccessAdjustment` porque ese
     * también concede acceso al solicitante y a responsables de ubicación,
     * lo que permitiría a alguien sin el rol admin rechazar una solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'El motivo de rechazo es requerido.',
            'rejection_reason.string' => 'El motivo de rechazo debe ser texto.',
            'rejection_reason.max' => 'El motivo de rechazo no puede exceder 1000 caracteres.',
        ];
    }
}
