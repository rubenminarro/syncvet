<?php

namespace App\Http\Requests;

use App\Services\ConsultationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una enmienda de una consulta cerrada (FASE 4, entrega 4.2; "Las enmiendas" del diseño, R-099):
 * content de 3 a 10000 caracteres, reason de 3 a 500 y field opcional, uno de los campos clínicos
 * de la consulta (ConsultationService::CLINICAL_FIELDS) o null para una nota general. Que esté
 * cerrada y sea de la ficha de quien enmienda lo revisa ConsultationService.
 */
class AmendConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3', 'max:' . ConsultationMessages::TEXT_MAX],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'field' => ['nullable', 'string', Rule::in(ConsultationService::CLINICAL_FIELDS)],
        ];
    }

    public function messages(): array
    {
        return [
            'field.in' => 'El campo enmendado debe ser uno de: ' . implode(', ', ConsultationService::CLINICAL_FIELDS) . '.',
        ];
    }
}
