<?php

namespace App\Http\Requests;

use App\Enums\ClientKind;
use App\Rules\RucCheckDigit;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Sin kind, persona.
        $isCompany = $this->input('kind') === ClientKind::COMPANY->value;

        return [
            'kind' => ['sometimes', Rule::enum(ClientKind::class)],
            'business_name' => [
                Rule::requiredIf($isCompany),
                Rule::prohibitedIf(! $isCompany),
                'nullable', 'string', 'min:2', 'max:150',
                // Mismos caracteres que el nombre de la factura.
                'regex:/^[\pL\pN\s.,\-&()\']+$/u',
            ],
            'document_number' => [
                'required', 'string', 'min:5', 'max:20',
                Rule::unique('clients', 'document_number')->where('clinic_id', CurrentClinic::id()),
                'regex:/^[a-zA-Z0-9-]+$/',
                new RucCheckDigit(),
            ],
            'first_name' => [
                Rule::requiredIf(! $isCompany),
                Rule::prohibitedIf($isCompany),
                'nullable', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u',
            ],
            'last_name' => [
                Rule::requiredIf(! $isCompany),
                Rule::prohibitedIf($isCompany),
                'nullable', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u',
            ],
            'email' => [
                'required',
                config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc',
                'max:50', 'lowercase',
                Rule::unique('clients', 'email')->where('clinic_id', CurrentClinic::id()),
            ],
            'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'address' => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pN\s.,#\-\/°º&()\']+$/u'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\s.\-]+$/u'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000', 'regex:/^[\pL\pN\s.,;:()\-#@!?]*$/u'],
        ];
    }

    public function messages(): array
    {
        return ClientMessages::all();
    }
}
