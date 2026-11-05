<?php

namespace App\Http\Requests;

use App\Enums\PatientSex;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de paciente bajo un cliente: el client_id sale de la URL
 * (clients/{client}/patients). Especies y razas: las globales (clinic_id nulo) o
 * las de la clínica actual.
 */
class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100', 'regex:/^[\pL\pN\s.\-\']+$/u'],
            'species_id' => ['required', 'integer', Rule::exists('species', 'id')->where('is_active', true)->where(fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', CurrentClinic::id()))],
            'breed_id' => ['nullable', 'integer', Rule::exists('breeds', 'id')->where('is_active', true)->where(fn ($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', CurrentClinic::id()))],
            'sex' => ['sometimes', Rule::enum(PatientSex::class)],
            'is_neutered' => ['sometimes', 'boolean'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_date_estimated' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\s,\-]+$/u'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:2000', 'decimal:0,2'],
            'microchip' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('patients', 'microchip')->where('clinic_id', CurrentClinic::id())],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return PatientMessages::all();
    }
}
