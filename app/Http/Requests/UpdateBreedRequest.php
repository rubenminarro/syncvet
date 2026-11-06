<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial). La raza no cambia de especie: species_id es prohibited. Una raza global
 * responde 403 (BreedService).
 */
class UpdateBreedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'species_id' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:80', 'regex:' . CatalogMessages::NAME_REGEX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return CatalogMessages::breed();
    }
}
