<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sumar un servicio a la consulta (FASE 4, entrega 4.3): service_id (de la clínica, no eliminado) y
 * quantity obligatorios; unit_price (por defecto, el del catálogo), discount (0 por defecto) y
 * veterinarian_id opcionales (ConsultationItemMessages).
 */
class StoreConsultationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ConsultationItemMessages::amountRules();

        return [
            'service_id' => [
                'required', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', CurrentClinic::id())->whereNull('deleted_at'),
            ],
            ...$rules,
            'quantity' => ['required', ...$rules['quantity']],
        ];
    }

    public function messages(): array
    {
        return [
            ...ConsultationItemMessages::messages(),
            'service_id.exists' => ConsultationItemMessages::SERVICE_NOT_AVAILABLE,
        ];
    }
}
