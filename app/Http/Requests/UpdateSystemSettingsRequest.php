<?php

namespace App\Http\Requests;

use App\Enums\SystemSettingKey;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de la configuración de la clínica (FASE 2, entrega
 * 2.5): name (nombre de fantasía) y legal_name (razón social); settings.<clave> con la
 * regla de cada clave de SystemSettingKey (null borra el valor). Una clave fuera de la
 * lista, también la interna logo_path, → 422. El RUC no se edita (pregunta 24).
 */
class UpdateSystemSettingsRequest extends FormRequest
{
    public const UNKNOWN_KEY = 'La clave :key no existe en la configuración.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * El correo de contacto, en minúsculas antes de validar (como el login).
     */
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');
        $key = SystemSettingKey::CONTACT_EMAIL->value;

        if (is_array($settings) && is_string($settings[$key] ?? null)) {
            $settings[$key] = mb_strtolower($settings[$key]);
            $this->merge(['settings' => $settings]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'legal_name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'ruc' => ['prohibited'],
            'ruc_dv' => ['prohibited'],
            'settings' => ['sometimes', 'array'],
        ];

        foreach (SystemSettingKey::editable() as $key) {
            $rules["settings.{$key->value}"] = ['sometimes', 'nullable', ...$key->rules()];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la clínica es obligatorio.',
            'name.string' => 'El nombre de la clínica debe tener el formato correcto.',
            'name.min' => 'El nombre de la clínica debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre de la clínica no debe tener más de 100 caracteres.',
            'legal_name.required' => 'La razón social es obligatoria.',
            'legal_name.string' => 'La razón social debe tener el formato correcto.',
            'legal_name.min' => 'La razón social debe tener al menos 2 caracteres.',
            'legal_name.max' => 'La razón social no debe tener más de 150 caracteres.',
            'ruc.prohibited' => 'El RUC de la clínica no se modifica desde la configuración.',
            'ruc_dv.prohibited' => 'El RUC de la clínica no se modifica desde la configuración.',
            'settings.array' => 'La configuración debe ser una lista de claves y valores.',
            'settings.contact_email.string' => 'El correo de contacto debe tener el formato correcto.',
            'settings.contact_email.max' => 'El correo de contacto no debe tener más de 150 caracteres.',
            'settings.contact_email.email' => 'El correo de contacto debe tener el formato correcto.',
            'settings.contact_phone.string' => 'El teléfono de contacto debe tener el formato correcto.',
            'settings.contact_phone.regex' => 'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
            'settings.website.string' => 'El sitio web debe tener el formato correcto.',
            'settings.website.max' => 'El sitio web no debe tener más de 150 caracteres.',
            'settings.website.url' => 'El sitio web debe ser una dirección que empiece con https://.',
            'settings.document_footer.string' => 'El pie de recetas y comprobantes debe tener el formato correcto.',
            'settings.document_footer.max' => 'El pie de recetas y comprobantes no debe tener más de 500 caracteres.',
        ];
    }

    /**
     * Una clave fuera de la lista (también logo_path, que es interna) → 422 en esa clave.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $settings = $this->input('settings');

                if (! is_array($settings)) {
                    return;
                }

                $editable = array_map(fn (SystemSettingKey $key) => $key->value, SystemSettingKey::editable());

                foreach (array_keys($settings) as $key) {
                    if (! in_array((string) $key, $editable, true)) {
                        $validator->errors()->add("settings.{$key}", str_replace(':key', (string) $key, self::UNKNOWN_KEY));
                    }
                }
            },
        ];
    }
}
