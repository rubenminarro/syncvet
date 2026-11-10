<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de usuarios (FASE 2, entrega 2.2a): los de ListFilters
 * (search en nombre, apellido y email), role y branch_id (una sucursal de la
 * clínica, activa o no) e include_inactive (por defecto, solo los activos; 2.2b).
 */
class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'role' => ['nullable', 'string', Rule::in(UserRole::values())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('clinic_id', CurrentClinic::id())],
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'El rol no es válido: los roles son ' . implode(', ', UserRole::values()) . '.',
            'branch_id.exists' => 'La sucursal no existe.',
        ];
    }
}
