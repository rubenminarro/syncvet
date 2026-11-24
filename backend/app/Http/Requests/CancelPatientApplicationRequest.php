<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Anular una aplicación (FASE 4, entrega 4.4): el motivo es obligatorio, de 3 a 500 caracteres, como el
 * de la anulación de una consulta. Quién puede y en qué estado lo revisa PatientApplicationService.
 */
class CancelPatientApplicationRequest extends FormRequest
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
        return (new CancelConsultationRequest())->messages();
    }
}
