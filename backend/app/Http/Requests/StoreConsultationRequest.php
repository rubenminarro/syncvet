<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Abrir una consulta (FASE 4, entrega 4.1): con appointment_id (desde el turno) o con
 * patient_id (sin turno), uno de los dos; opcionales veterinarian_id (obligatorio para quien no
 * tiene ficha, si el turno no tiene veterinario), consulted_at (por defecto, ahora) y los campos
 * clínicos (ConsultationMessages). Las referencias, de la clínica; el estado del turno, el
 * paciente fallecido, el veterinario activo y el fichado los revisa ConsultationService con las
 * filas bloqueadas.
 */
class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            'appointment_id' => [
                'nullable', 'integer', 'required_without:patient_id', 'prohibits:patient_id',
                Rule::exists('appointments', 'id')->where('clinic_id', $clinicId),
            ],
            'patient_id' => [
                'nullable', 'integer', 'required_without:appointment_id',
                Rule::exists('patients', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'veterinarian_id' => [
                'nullable', 'integer',
                Rule::exists('veterinarians', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            ...ConsultationMessages::clinicalRules(),
        ];
    }

    public function messages(): array
    {
        return [
            ...ConsultationMessages::messages(),
            'appointment_id.required_without' => 'Ingresá el turno o el paciente de la consulta.',
            'patient_id.required_without' => 'Ingresá el turno o el paciente de la consulta.',
            'appointment_id.prohibits' => 'Ingresá el turno o el paciente, no los dos: con el turno, el paciente es el del turno.',
            'appointment_id.exists' => 'El turno no existe.',
            'patient_id.exists' => 'El paciente no existe.',
            'veterinarian_id.exists' => 'El veterinario no existe.',
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => ConsultationMessages::validateNotFuture($validator, $this->input('consulted_at')),
        ];
    }
}
