<?php

namespace App\Http\Requests;

use App\Enums\PatientSex;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * PATCH. El dueño no se cambia aquí: POST patients/{patient}/change-owner.
 * La especie y la raza: una activa o la que el paciente ya tiene, aunque se haya
 * inactivado (así el formulario puede reenviarla). custom_fields no forma parte
 * de la API (R-015).
 */
class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'client_id' => ['prohibited'],
            'code' => ['prohibited'],
            'name' => ['sometimes', 'string', 'min:1', 'max:100', 'regex:/^[\pL\pN\s.\-\']+$/u'],
            'species_id' => ['sometimes', 'integer', $this->activeOrCurrent('species', $patient->species_id)],
            'breed_id' => ['sometimes', 'nullable', 'integer', $this->activeOrCurrent('breeds', $patient->breed_id)],
            'sex' => ['sometimes', Rule::enum(PatientSex::class)],
            'is_neutered' => ['sometimes', 'boolean'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_date_estimated' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[\pL\s,\-]+$/u'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:2000', 'decimal:0,2'],
            'microchip' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('patients', 'microchip')->where('clinic_id', CurrentClinic::id())->ignore($patient)],
            'allergies' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'deceased_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return PatientMessages::all();
    }

    /**
     * La fecha de fallecimiento no es anterior a la de nacimiento (la que llega o la
     * que el paciente ya tiene; revisión de la entrega 1.3, R-021). Se compara el día
     * en la hora de Asunción: deceased_at llega en UTC y birth_date es una fecha local.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['deceased_at', 'birth_date']) || ! $this->filled('deceased_at')) {
                    return;
                }

                $birthDate = $this->has('birth_date')
                    ? $this->input('birth_date')
                    : $this->route('patient')->birth_date?->toDateString();

                $deceasedOn = Date::parse($this->input('deceased_at'))->setTimezone(config('app.timezone'))->toDateString();

                if ($birthDate !== null && $deceasedOn < $birthDate) {
                    $validator->errors()->add('deceased_at', PatientMessages::DECEASED_BEFORE_BIRTH);
                }
            },
        ];
    }

    /**
     * Global o de la clínica actual, y activa o la que el paciente ya tiene.
     */
    private function activeOrCurrent(string $table, ?int $currentId): Exists
    {
        return Rule::exists($table, 'id')
            ->where(fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', CurrentClinic::id()))
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId !== null, fn ($q) => $q->orWhere('id', $currentId)));
    }
}
