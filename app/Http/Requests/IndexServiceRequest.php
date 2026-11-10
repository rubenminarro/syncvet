<?php

namespace App\Http\Requests;

use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Filtros del listado de servicios (FASE 2, entrega 2.4): los de ListFilters (search en
 * código y nombre), category, status (manda; sin él, solo los activos, salvo
 * include_inactive=1), como veterinarios.
 */
class IndexServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'category' => ['nullable', Rule::enum(ServiceCategory::class)],
            'status' => ['nullable', Rule::enum(ServiceStatus::class)],
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $messages = ServiceMessages::messages();

        return [
            'category.' . Enum::class => $messages['category.' . Enum::class],
            'status.' . Enum::class => $messages['status.' . Enum::class],
        ];
    }
}
