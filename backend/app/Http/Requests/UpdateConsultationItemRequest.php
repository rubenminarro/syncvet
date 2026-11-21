<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editar un ítem de servicio de una consulta abierta (FASE 4, entrega 4.3), con semántica parcial: la
 * cantidad (no null), el precio unitario, el descuento y el veterinario. El servicio, la consulta, el
 * tipo, la descripción, la tasa de IVA, el total y el estado de cobro no se cambian (prohibited): para
 * otro servicio, se quita el ítem y se suma otro.
 */
class UpdateConsultationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = array_map(fn (array $rules) => ['sometimes', ...$rules], ConsultationItemMessages::amountRules());
        $rules['quantity'] = ['sometimes', 'required', ...ConsultationItemMessages::amountRules()['quantity']];

        foreach (['service_id', 'consultation_id', 'type', 'description', 'tax_rate', 'total', 'billing_status'] as $fixed) {
            $rules[$fixed] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...ConsultationItemMessages::messages(),
            'quantity.required' => 'Ingresá la cantidad.',
            'prohibited' => 'El campo :attribute no se puede cambiar en un ítem: quitalo y sumá otro.',
        ];
    }
}
