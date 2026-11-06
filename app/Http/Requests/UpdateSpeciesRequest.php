<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial): solo se validan los campos enviados. Una especie global responde 403
 * (authorize, antes de validar, y SpeciesService).
 */
class UpdateSpeciesRequest extends FormRequest
{
    /**
     * Una global responde 403 CATALOG_GLOBAL_NOT_EDITABLE antes de validar el cuerpo
     * (revisión de la entrega 1.4): el Form Request corre antes que el Service, y sin
     * esto un cuerpo inválido respondía 422. El Service lo vuelve a revisar.
     */
    public function authorize(): bool
    {
        $this->route('species')->ensureNotGlobal();

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
