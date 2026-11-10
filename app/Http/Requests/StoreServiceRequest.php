<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un servicio (FASE 2, entrega 2.4). Nombre, categoría y precio obligatorios;
 * duración (30), tasa de IVA (10) y estado (active), con el valor por defecto de la
 * columna si no llegan. code lo asigna la serie SRV- (prohibited).
 */
class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = ServiceMessages::dataRules();

        return [
            ...$data,
            'duration_minutes' => ['sometimes', ...$data['duration_minutes']],
            'tax_rate' => ['sometimes', ...$data['tax_rate']],
            'status' => ['sometimes', ...$data['status']],
        ];
    }

    public function messages(): array
    {
        return ServiceMessages::messages();
    }
}
