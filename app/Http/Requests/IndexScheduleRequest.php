<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de agendas (FASE 3, entrega 3.1): los de ListFilters (search literal
 * en el nombre), branch_id, veterinarian_id y service_id (de la clínica, activos o no) e
 * include_inactive (sin él, solo las activas).
 */
class IndexScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            ...ListFilters::rules(),
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('clinic_id', $clinicId)],
            'veterinarian_id' => ['nullable', 'integer', Rule::exists('veterinarians', 'id')->where('clinic_id', $clinicId)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.exists' => 'La sucursal no existe.',
            'veterinarian_id.exists' => 'El veterinario no existe.',
            'service_id.exists' => 'El servicio no existe.',
        ];
    }
}
