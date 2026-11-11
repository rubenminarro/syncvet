<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtro de los listados del catálogo (GET species y GET species/{species}/breeds):
 * por defecto, solo las activas; include_inactive=1 trae también las inactivas.
 * Sin paginación ni búsqueda: el catálogo de una clínica es corto. Sin mensajes
 * propios: salen de lang/es/validation.php.
 */
class IndexCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }
}
