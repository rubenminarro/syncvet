<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un turno (FASE 3, entrega 3.2a; P2, P4 a P6). schedule_id y starts_at
 * obligatorios; ends_at opcional (por defecto, la duración del servicio o slot_minutes de la
 * agenda; lo calcula AppointmentService). Uno de tres: el paciente, un cliente sin paciente o
 * el nombre y el teléfono de contacto juntos; los datos de contacto, solo sin cliente ni
 * paciente (null se acepta: el frontend manda el formulario completo).
 *
 * overbook: true necesita el permiso appointments.overbook: sin él, 403 (authorize()).
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
            ...AppointmentMessages::prohibitedRules(),
        ];
    }

    public function messages(): array
    {
        return AppointmentMessages::messages();
    }

    public function after(): array
    {
        return [
            fn ($validator) => AppointmentMessages::validateTimes($validator, $this->input('starts_at'), $this->input('ends_at')),
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
}
