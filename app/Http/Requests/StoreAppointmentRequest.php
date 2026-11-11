<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use App\Support\AppointmentRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Alta de un turno (FASE 3, entrega 3.2a; P2, P4 a P6). schedule_id y starts_at
 * obligatorios; ends_at opcional (por defecto, la duración del servicio o slot_minutes de la
 * agenda; lo calcula AppointmentService). Uno de tres: el paciente, un cliente sin paciente o
 * el nombre y el teléfono de contacto juntos; los datos de contacto, solo sin cliente ni
 * paciente (null se acepta: el frontend manda el formulario completo).
 *
 * overbook: true necesita el permiso appointments.overbook: sin él, 403 (authorize()).
 *
 * Serie (3.3, P1): recurrence con every_weeks (1 a 12, 1 por defecto) y uno de count (2 a 52)
 * o until (una fecha, desde la del primer turno y hasta un año después, que dé de 2 a 52
 * turnos). overbook: true vale para todas las fechas de la serie (precisión del usuario en la
 * 3.3: el diseño lo prohibía).
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! AppointmentMessages::wantsOverbook($this->input('overbook'))
            || $this->user()->can('appointments.overbook');
    }

    public function rules(): array
    {
        $data = AppointmentMessages::dataRules();

        return [
            ...$data,
            'source' => ['sometimes', ...$data['source']],
            'overbook' => ['sometimes', ...$data['overbook']],
            ...AppointmentMessages::recurrenceRules(),
            ...AppointmentMessages::prohibitedRules(),
        ];
    }

    public function messages(): array
    {
        return [...AppointmentMessages::messages(), ...AppointmentMessages::recurrenceMessages()];
    }

    public function after(): array
    {
        return [
            fn ($validator) => AppointmentMessages::validateTimes($validator, $this->input('starts_at'), $this->input('ends_at')),
            fn ($validator) => $this->validateRecurrence($validator),
            function ($validator) {
                $hasPatientOrClient = $this->filled('patient_id') || $this->filled('client_id');
                $hasName = $this->filled('contact_name');
                $hasPhone = $this->filled('contact_phone');

                if ($hasPatientOrClient) {
                    if ($hasName || $hasPhone) {
                        $validator->errors()->add($hasName ? 'contact_name' : 'contact_phone', AppointmentMessages::CONTACT_PROHIBITED);
                    }

                    return;
                }

                if ($hasName && ! $hasPhone) {
                    $validator->errors()->add('contact_phone', AppointmentMessages::CONTACT_TOGETHER);
                } elseif ($hasPhone && ! $hasName) {
                    $validator->errors()->add('contact_name', AppointmentMessages::CONTACT_TOGETHER);
                } elseif (! $hasName) {
                    $validator->errors()->add('patient_id', AppointmentMessages::WHO_REQUIRED);
                }
            },
        ];
    }

    /**
     * Uno de count o until, no los dos ni ninguno. Con until: desde la fecha del primer turno
     * (en Asunción), hasta un año después, y que dé de 2 a 52 turnos.
     */
    private function validateRecurrence(Validator $validator): void
    {
        $recurrence = $this->input('recurrence');

        if (! is_array($recurrence) || $validator->errors()->hasAny(['recurrence', 'recurrence.*'])) {
            return;
        }

        $hasCount = ($recurrence['count'] ?? null) !== null;
        $hasUntil = ($recurrence['until'] ?? null) !== null;

        if ($hasCount === $hasUntil) {
            $validator->errors()->add('recurrence.count', $hasCount ? AppointmentMessages::RECURRENCE_BOTH : AppointmentMessages::RECURRENCE_END_REQUIRED);

            return;
        }

        $startsAt = $this->input('starts_at');

        if (! $hasUntil || ! is_string($startsAt) || $validator->errors()->has('starts_at')) {
            return;
        }

        $first = ApiDateTime::toLocal($startsAt);
        $until = (string) $recurrence['until'];

        if ($until < $first->toDateString()) {
            $validator->errors()->add('recurrence.until', AppointmentMessages::RECURRENCE_UNTIL_BEFORE);
        } elseif ($until > $first->addYear()->toDateString()) {
            $validator->errors()->add('recurrence.until', AppointmentMessages::RECURRENCE_UNTIL_TOO_FAR);
        } else {
            $count = AppointmentRecurrence::count($first, (int) ($recurrence['every_weeks'] ?? 1), null, $until);

            if ($count > AppointmentRecurrence::MAX_COUNT) {
                $validator->errors()->add('recurrence.until', AppointmentMessages::RECURRENCE_TOO_MANY);
            } elseif ($count < AppointmentRecurrence::MIN_COUNT) {
                $validator->errors()->add('recurrence.until', AppointmentMessages::RECURRENCE_TOO_FEW);
            }
        }
    }
}
