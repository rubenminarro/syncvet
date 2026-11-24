<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Anular una consulta (FASE 4, entrega 4.2): el motivo es obligatorio, de 3 a 500 caracteres, como
 * el de la cancelación de un turno. Que no esté ya anulada lo revisa ConsultationService.
 */
class CancelConsultationRequest extends FormRequest
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
            'cancellation_reason.required' => 'Ingresá el motivo de la anulación.',
            'cancellation_reason.string' => 'El motivo de la anulación debe tener el formato correcto.',
            'cancellation_reason.min' => 'El motivo de la anulación debe tener al menos 3 caracteres.',
            'cancellation_reason.max' => 'El motivo de la anulación no debe tener más de 500 caracteres.',
        ];
    }
}
