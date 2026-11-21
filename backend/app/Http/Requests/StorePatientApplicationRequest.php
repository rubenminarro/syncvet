<?php

namespace App\Http\Requests;

use App\Enums\PatientApplicationType;
use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cargar una aplicación (FASE 4, entrega 4.4): type, name y applied_at (un instante, no futuro)
 * obligatorios; opcionales consultation_id (de la clínica; que sea del paciente y esté abierta lo
 * revisa PatientApplicationService), lot_number, lot_expires_at (fecha), dose, route, dose_number (1 a
 * 255), next_dose_due_at (fecha, posterior al día de aplicación en Asunción) y notes.
 */
class StorePatientApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(PatientApplicationType::values())],
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'applied_at' => ['required', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'consultation_id' => ['nullable', 'integer', Rule::exists('consultations', 'id')->where('clinic_id', CurrentClinic::id())],
            'lot_number' => ['nullable', 'string', 'max:50'],
            'lot_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'dose' => ['nullable', 'string', 'max:50'],
            'route' => ['nullable', 'string', 'max:30'],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:255'],
            'next_dose_due_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $types = implode(', ', PatientApplicationType::values());

        return [
            'type.in' => "El tipo debe ser uno de: {$types}.",
            'applied_at.string' => AppointmentMessages::DATE_TIME_FORMAT,
            'applied_at.regex' => AppointmentMessages::DATE_TIME_FORMAT,
            'applied_at.date' => AppointmentMessages::DATE_TIME_FORMAT,
            'consultation_id.exists' => 'La consulta no existe.',
            'lot_expires_at.date_format' => PatientApplicationMessages::DATE_FORMAT,
            'next_dose_due_at.date_format' => PatientApplicationMessages::DATE_FORMAT,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('applied_at')) {
                    return;
                }

                $appliedAt = ApiDateTime::toLocal((string) $this->input('applied_at'));

                if (ApiDateTime::floorSecond($appliedAt)->greaterThan(now())) {
                    $validator->errors()->add('applied_at', PatientApplicationMessages::APPLIED_AT_FUTURE);
                }

                $next = $this->input('next_dose_due_at');

                if (is_string($next) && ! $validator->errors()->has('next_dose_due_at') && $next <= $appliedAt->toDateString()) {
                    $validator->errors()->add('next_dose_due_at', PatientApplicationMessages::NEXT_DOSE_NOT_AFTER);
                }
            },
        ];
    }
}
