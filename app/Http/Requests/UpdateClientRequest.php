<?php

namespace App\Http\Requests;

use App\Enums\ClientKind;
use App\Rules\RucCheckDigit;
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

        // Al cambiar de tipo, los campos del tipo nuevo son obligatorios. No van con
        // "sometimes": con él, un campo ausente saltea todas sus reglas, también
        // required_if, y quedaba una empresa sin razón social (entrega 1.2).
        $companyFields = $isCompany && $changesKind ? 'required' : 'sometimes';
        $personFields = ! $isCompany && $changesKind ? 'required' : 'sometimes';

        return [
            'kind' => ['sometimes', Rule::enum(ClientKind::class)],
            'business_name' => [
                $companyFields,
                Rule::prohibitedIf(! $isCompany),
                'string', 'min:2', 'max:150',
                'regex:/^[\pL\pN\s.,\-&()\']+$/u',
            ],
            'document_number' => [
                'sometimes', 'string', 'min:5', 'max:20',
                Rule::unique('clients', 'document_number')->where('clinic_id', CurrentClinic::id())->ignore($client),
                'regex:/^[a-zA-Z0-9-]+$/',
                new RucCheckDigit(),
            ],
            'first_name' => [
                $personFields,
                Rule::prohibitedIf($isCompany),
                'string', 'min:2', 'max:100',
                'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u',
            ],
            'last_name' => [
                $personFields,
                Rule::prohibitedIf($isCompany),
                'string', 'min:2', 'max:100',
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
