<?php

namespace App\Http\Requests;

use App\Enums\PatientSex;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH. El dueño no se cambia aquí: POST patients/{patient}/change-owner.
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
            'species_id' => ['sometimes', 'integer', Rule::exists('species', 'id')->where('is_active', true)->where(fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', CurrentClinic::id()))],
            'breed_id' => ['sometimes', 'nullable', 'integer', Rule::exists('breeds', 'id')->where('is_active', true)->where(fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', CurrentClinic::id()))],
            'sex' => ['sometimes', Rule::enum(PatientSex::class)],
            'is_neutered' => ['sometimes', 'boolean'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_date_estimated' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[\pL\s,\-]+$/u'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:2000', 'decimal:0,2'],
            'microchip' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('patients', 'microchip')->where('clinic_id', CurrentClinic::id())->ignore($patient)],
            'allergies' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'deceased_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return PatientMessages::all();
    }
}
