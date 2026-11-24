<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Editar una consulta abierta (FASE 4, entrega 4.1), con semántica parcial: solo cambian los
 * campos que llegan. consulted_at, si llega, no es null. El turno, el paciente, el cliente, el
 * veterinario, la sucursal y el estado no se editan (prohibited). Que esté abierta y sea de la
 * ficha de quien edita lo revisa ConsultationService, con la consulta bloqueada.
 */
class UpdateConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = array_map(fn (array $rules) => ['sometimes', ...$rules], ConsultationMessages::clinicalRules());
        $rules['consulted_at'] = ['sometimes', 'required', ...array_slice($rules['consulted_at'], 2)];

        foreach (['appointment_id', 'patient_id', 'client_id', 'veterinarian_id', 'branch_id', 'status', 'code'] as $fixed) {
            $rules[$fixed] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...ConsultationMessages::messages(),
            'consulted_at.required' => 'Ingresá la fecha de la consulta.',
            'prohibited' => 'El campo :attribute no se puede cambiar en una consulta.',
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => ConsultationMessages::validateNotFuture($validator, $this->input('consulted_at')),
        ];
    }
}
