<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta (o restauración, P6) de un veterinario (FASE 2, entrega 2.3). user_id: un usuario
 * activo de la clínica; el rol veterinario y la ficha que ya tenga los revisa
 * VeterinarianService con el usuario bloqueado. code lo asigna la serie (prohibited).
 */
class StoreVeterinarianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = VeterinarianMessages::dataRules();

        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')
                    ->where('clinic_id', CurrentClinic::id())
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            ...$data,
            // Sin enviar, los valores por defecto de la columna (0 y active).
            'commission_percentage' => ['sometimes', ...$data['commission_percentage']],
            'status' => ['sometimes', ...$data['status']],
        ];
    }

    public function messages(): array
    {
        return VeterinarianMessages::messages();
    }
}
