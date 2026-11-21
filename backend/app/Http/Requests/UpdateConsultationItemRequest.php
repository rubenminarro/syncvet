<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editar un ítem de servicio de una consulta abierta (FASE 4, entrega 4.3), con semántica parcial: la
 * cantidad, el precio unitario, el descuento y el veterinario. Lo que no se quiere cambiar no se manda:
 * ninguno acepta null (422; revisión de la 4.3). El servicio, la consulta, el tipo, la descripción, la
 * tasa de IVA, el total y el estado de cobro no se cambian (prohibited): para otro servicio, se quita
 * el ítem y se suma otro.
 */
class UpdateConsultationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'sometimes' y 'required': si llega, no puede ser null ni vacío (el 0 de un importe sí vale).
        $rules = array_map(
            fn (array $rules) => ['sometimes', 'required', ...array_values(array_filter($rules, fn ($rule) => $rule !== 'nullable'))],
            ConsultationItemMessages::amountRules()
        );

        foreach (['service_id', 'consultation_id', 'type', 'description', 'tax_rate', 'total', 'billing_status'] as $fixed) {
            $rules[$fixed] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...ConsultationItemMessages::messages(),
            'quantity.required' => 'Ingresá la cantidad, o no la mandes si no cambia.',
            'unit_price.required' => 'Ingresá el precio unitario, o no lo mandes si no cambia.',
            'discount.required' => 'Ingresá el descuento (0 para quitarlo), o no lo mandes si no cambia.',
            'veterinarian_id.required' => 'Ingresá el veterinario, o no lo mandes si no cambia.',
            'prohibited' => 'El campo :attribute no se puede cambiar en un ítem: quitalo y sumá otro.',
        ];
    }
}
