<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una raza propia en species/{species}/breeds: la especie sale de la URL
 * (un species_id en el cuerpo se ignora, como el client_id del alta de un
 * paciente). El nombre único dentro de la especie lo revisa BreedService.
 */
class StoreBreedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:' . CatalogMessages::NAME_REGEX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return CatalogMessages::breed();
    }
}
