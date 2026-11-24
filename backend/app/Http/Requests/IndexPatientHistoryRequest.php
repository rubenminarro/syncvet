<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El historial del paciente (FASE 4, entrega 4.6; pregunta 18): solo la paginación de ListFilters (sin
 * search ni otros filtros), de a 20 por defecto y hasta 100.
 */
class IndexPatientHistoryRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ListFilters::rules()['per_page'],
            'page' => ListFilters::rules()['page'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
