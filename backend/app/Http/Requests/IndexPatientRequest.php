<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros del listado de pacientes. En un Form Request, como toda validación
 * (FASE 1, entrega 1.2, deuda 11): así ValidationTranslationsTest revisa el
 * mensaje y el nombre en español de cada campo. Sin mensajes propios: salen de
 * lang/es/validation.php.
 */
class IndexPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'species_id' => ['nullable', 'integer'],
            'include_deceased' => ['nullable', 'boolean'],
        ];
    }
}
