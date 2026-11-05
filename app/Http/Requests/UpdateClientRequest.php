<?php

namespace App\Http\Requests;

use App\Enums\ClientKind;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: solo se validan los campos enviados. Los opcionales que el alta acepta
 * como null también se pueden vaciar.
 */
class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $kind = $this->input('kind', $client?->kind?->value);
        $isCompany = $kind === ClientKind::COMPANY->value;
        $changesKind = $this->filled('kind') && $this->input('kind') !== $client?->kind?->value;

        return [
            'kind' => ['sometimes', Rule::enum(ClientKind::class)],
            'business_name' => [
                Rule::requiredIf($isCompany && $changesKind),
                Rule::prohibitedIf(! $isCompany),
                'sometimes', 'string', 'min:2', 'max:150',
                'regex:/^[\pL\pN\s.,\-&()\']+$/u',
            ],
            'document_number' => [
                'sometimes', 'string', 'min:5', 'max:20',
                Rule::unique('clients', 'document_number')->where('clinic_id', CurrentClinic::id())->ignore($client),
                'regex:/^[a-zA-Z0-9-]+$/',
            ],
            'first_name' => [
                Rule::requiredIf(! $isCompany && $changesKind),
                Rule::prohibitedIf($isCompany),
                'sometimes', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u',
            ],
            'last_name' => [
                Rule::requiredIf(! $isCompany && $changesKind),
                Rule::prohibitedIf($isCompany),
                'sometimes', 'string', 'min:2', 'max:100',
                'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u',
            ],
            'email' => [
                'sometimes',
                config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc',
                'max:50', 'lowercase',
                Rule::unique('clients', 'email')->where('clinic_id', CurrentClinic::id())->ignore($client),
            ],
            'phone' => ['sometimes', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[\pL\pN\s.,#\-\/°º&()\']+$/u'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[\pL\s.\-]+$/u'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000', 'regex:/^[\pL\pN\s.,;:()\-#@!?]*$/u'],
        ];
    }

    public function messages(): array
    {
        return ClientMessages::all();
    }
}
