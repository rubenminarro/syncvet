<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtro del listado de los pacientes de un cliente (clients/{client}/patients):
 * oculta a los fallecidos salvo include_deceased=1, como el listado general
 * (CLAUDE.md §6.2; revisión de la entrega 1.3, R-021). Sin paginación ni
 * búsqueda: son los pacientes de un solo cliente. Sin mensajes propios: salen de
 * lang/es/validation.php.
 */
class IndexClientPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_deceased' => ['nullable', 'boolean'],
        ];
    }
}
