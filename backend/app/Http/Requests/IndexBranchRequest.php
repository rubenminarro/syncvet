<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros del listado de sucursales (FASE 2, entrega 2.1): los de ListFilters
 * (search en código y nombre) e include_inactive (por defecto, solo las activas).
 * Sin mensajes propios: salen de lang/es/validation.php.
 */
class IndexBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }
}
