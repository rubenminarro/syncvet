<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial): solo se validan los campos enviados. Una especie global responde 403
 * (SpeciesService).
 */
class UpdateSpeciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:60', 'regex:' . CatalogMessages::NAME_REGEX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return CatalogMessages::species();
    }
}
