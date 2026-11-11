<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de un turno (FASE 3, entrega 3.2a; P7): solo se validan los
 * campos enviados. Mover es cambiar starts_at, ends_at o schedule_id. Que el turno sea
 * editable (a confirmar o confirmado), el paciente, el cliente o el contacto que queda, el
 * horario, los bloqueos y la superposición los revisa AppointmentService con las filas
 * bloqueadas.
 *
 * overbook: true necesita el permiso appointments.overbook: sin él, 403 (authorize()).
 */
class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! AppointmentMessages::wantsOverbook($this->input('overbook'))
            || $this->user()->can('appointments.overbook');
    }

    public function rules(): array
    {
        return [
            ...array_map(fn (array $rules) => ['sometimes', ...$rules], AppointmentMessages::dataRules()),
            ...AppointmentMessages::prohibitedRules(),
            // La serie se arma en el alta (3.3): cada turno se mueve solo y conserva su series_id.
            'recurrence' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...AppointmentMessages::messages(),
            'recurrence.prohibited' => 'La serie se arma al dar los turnos: cada turno se modifica por separado.',
        ];
    }

    public function after(): array
    {
        return [
            fn ($validator) => AppointmentMessages::validateTimes($validator, $this->input('starts_at'), $this->input('ends_at')),
        ];
    }
}
