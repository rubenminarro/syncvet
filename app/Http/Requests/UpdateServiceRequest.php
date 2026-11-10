<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de un servicio (FASE 2, entrega 2.4): solo se validan
 * los campos enviados. El código no cambia (prohibited). El precio se edita siempre (lo
 * usado guarda su precio); el nombre de un servicio usado, no (lo revisa el Service).
 */
class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = ServiceMessages::dataRules();

        return [
            'code' => $data['code'],
            ...array_map(
                fn (array $rules) => ['sometimes', ...$rules],
                array_diff_key($data, ['code' => true])
            ),
        ];
    }

    public function messages(): array
    {
        return ServiceMessages::messages();
    }
}
