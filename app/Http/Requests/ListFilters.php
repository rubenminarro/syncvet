<?php

namespace App\Http\Requests;

/**
 * Filtros comunes de los listados (FASE 1, entrega 1.2): un filtro mal formado
 * responde 422, nunca 500. Sin mensajes propios: salen de lang/es/validation.php.
 */
class ListFilters
{
    public const DEFAULT_PER_PAGE = 10;

    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
