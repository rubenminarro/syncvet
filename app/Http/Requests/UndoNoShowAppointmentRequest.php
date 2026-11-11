<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deshacer el ausente (FASE 3, entrega 3.2b; pregunta 19 y decisión del usuario en la revisión):
 * overbook: true vuelve a ocupar el horario aunque otro turno lo haya tomado, como en el alta, y
 * necesita el permiso appointments.overbook (sin él, 403 en authorize()). Deshacer el ausente de
 * un turno que ya era sobreturno también lo exige: lo revisa AppointmentService::undoNoShow con
 * el turno bloqueado.
 */
class UndoNoShowAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! AppointmentMessages::wantsOverbook($this->input('overbook'))
            || $this->user()->can('appointments.overbook');
    }

    public function rules(): array
    {
        return [
            'overbook' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'overbook.boolean' => 'El sobreturno debe ser verdadero o falso.',
        ];
    }
}
