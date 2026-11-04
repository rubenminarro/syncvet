<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePatientOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'El nuevo dueño es obligatorio.',
            'client_id.integer' => 'El nuevo dueño debe tener el formato correcto.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
        ];
    }
}
