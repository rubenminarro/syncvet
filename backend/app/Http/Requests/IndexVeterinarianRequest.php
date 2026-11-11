<?php

namespace App\Http\Requests;

use App\Enums\VeterinarianStatus;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Filtros del listado de veterinarios (FASE 2, entrega 2.3): los de ListFilters
 * (search en código, nombre del usuario y matrícula), status (manda; sin él, solo los
 * activos, salvo include_inactive=1) y branch_id (una sucursal de la clínica, activa o
 * no: las de su usuario, P5).
 */
class IndexVeterinarianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'status' => ['nullable', Rule::enum(VeterinarianStatus::class)],
            'include_inactive' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('clinic_id', CurrentClinic::id())],
        ];
    }

    public function messages(): array
    {
        return [
            'status.' . Enum::class => VeterinarianMessages::messages()['status.' . Enum::class],
            'branch_id.exists' => 'La sucursal no existe.',
        ];
    }
}
