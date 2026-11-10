<?php

namespace App\Http\Requests;

use App\Models\Veterinarian;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de un veterinario (FASE 2, entrega 2.3): solo se
 * validan los campos enviados. El usuario y el código no cambian (prohibited). Se
 * inactiva aunque esté en uso (status inactive).
 */
class UpdateVeterinarianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(VeterinarianMessages::normalizedInput($this->all()));
    }

    public function rules(): array
    {
        $veterinarian = $this->route('veterinarian');
        $data = VeterinarianMessages::dataRules($veterinarian instanceof Veterinarian ? $veterinarian : null);

        return [
            'user_id' => ['prohibited'],
            'code' => $data['code'],
            ...array_map(
                fn (array $rules) => ['sometimes', ...$rules],
                array_diff_key($data, ['code' => true])
            ),
        ];
    }

    public function messages(): array
    {
        return VeterinarianMessages::messages();
    }
}
