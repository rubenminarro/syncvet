<?php

namespace App\Http\Requests;

use App\Enums\ClientKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Filtros del listado de clientes. En un Form Request, como toda validación
 * (FASE 1, entrega 1.2, deuda 11): así ValidationTranslationsTest revisa el
 * mensaje y el nombre en español de cada campo.
 */
class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(ClientKind::class)],
        ];
    }

    public function messages(): array
    {
        return ['kind.' . Enum::class => ClientMessages::all()['kind'][Enum::class]];
    }
}
