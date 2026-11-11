<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial). La raza no cambia de especie: species_id es prohibited. Una raza global
 * responde 403 (authorize, antes de validar, y BreedService).
 */
class UpdateBreedRequest extends FormRequest
{
    /**
     * Una global responde 403 CATALOG_GLOBAL_NOT_EDITABLE antes de validar el cuerpo
     * (revisión de la entrega 1.4): el Form Request corre antes que el Service, y sin
     * esto un cuerpo inválido respondía 422. El Service lo vuelve a revisar.
     */
    public function authorize(): bool
    {
        $this->route('breed')->ensureNotGlobal();

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
