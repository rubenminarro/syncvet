<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelar un turno (FASE 3, entrega 3.2b; P3): el motivo es obligatorio, de 3 a 500
 * caracteres. Sin límite de anticipación. La serie (scope) llega en la 3.3.
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Ingresá el motivo de la cancelación.',
            'cancellation_reason.string' => 'El motivo de la cancelación debe tener el formato correcto.',
            'cancellation_reason.min' => 'El motivo de la cancelación debe tener al menos 3 caracteres.',
            'cancellation_reason.max' => 'El motivo de la cancelación no debe tener más de 500 caracteres.',
        ];
    }
}
