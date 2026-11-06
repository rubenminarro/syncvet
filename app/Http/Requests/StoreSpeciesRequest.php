<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una especie propia de la clínica (FASE 1, entrega 1.4). El nombre único lo revisa SpeciesService.
 */
class StoreSpeciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:' . CatalogMessages::NAME_REGEX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return CatalogMessages::species();
    }
}
