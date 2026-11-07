<?php

namespace App\Http\Requests;

use App\Enums\ClientKind;
use App\Rules\RucCheckDigit;
use App\Rules\UniqueClientDocument;
use App\Support\CurrentClinic;
use App\Support\PersonName;
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

        // Como en el alta (entrega 1.2): un campo obligatorio enviado vacío responde
        // solo "es obligatorio" ("sometimes" + "required"); al cambiar de tipo, los
        // del tipo nuevo son obligatorios aunque no se envíen ("required" solo).
        // Los del otro tipo admiten null, porque el frontend manda todos los
        // campos, y rechazan cualquier valor.
        $ownKind = $changesKind ? ['required'] : ['sometimes', 'required'];
        $otherKind = ['prohibited', 'nullable'];

        return [
            'kind' => ['sometimes', Rule::enum(ClientKind::class)],
            'business_name' => [
                ...($isCompany ? $ownKind : $otherKind),
                'string', 'min:2', 'max:150',
                'regex:/^[\pL\pN\s.,\-&()\']+$/u',
            ],
            'document_number' => [
                'sometimes', 'required', 'string', 'min:5', 'max:20',
                'regex:/^[a-zA-Z0-9-]+$/',
                new RucCheckDigit(),
                // Único por número base: cédula y RUC son el mismo documento (R-018).
                new UniqueClientDocument($client),
            ],
            'first_name' => [
                ...(! $isCompany ? $ownKind : $otherKind),
                'string', 'min:2', 'max:100',
                'regex:' . PersonName::REGEX,
            ],
            'last_name' => [
                ...(! $isCompany ? $ownKind : $otherKind),
                'string', 'min:2', 'max:100',
                'regex:' . PersonName::REGEX,
            ],
            'email' => [
                'sometimes', 'required',
                config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc',
                'max:50', 'lowercase',
                Rule::unique('clients', 'email')->where('clinic_id', CurrentClinic::id())->ignore($client),
            ],
            'phone' => ['sometimes', 'required', 'regex:/^\+?[1-9]\d{7,14}$/'],
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
