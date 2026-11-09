<?php

namespace App\Enums;

/**
 * Claves de system_settings (FASE 2, entrega 2.5; P13 y pregunta 23): una lista
 * cerrada, cada una con su regla. logo_path es interna: la escribe ClinicLogoService,
 * no se edita por la API y nunca sale en una respuesta (has_logo en su lugar).
 */
enum SystemSettingKey: string
{
    case CONTACT_EMAIL = 'contact_email';
    case CONTACT_PHONE = 'contact_phone';
    case WEBSITE = 'website';
    case DOCUMENT_FOOTER = 'document_footer';
    case LOGO_PATH = 'logo_path';

    public function label(): string
    {
        return match ($this) {
            self::CONTACT_EMAIL => 'Correo de contacto',
            self::CONTACT_PHONE => 'Teléfono de contacto',
            self::WEBSITE => 'Sitio web',
            self::DOCUMENT_FOOTER => 'Pie de recetas y comprobantes',
            self::LOGO_PATH => 'Logo',
        };
    }

    public function isEditable(): bool
    {
        return $this !== self::LOGO_PATH;
    }

    /**
     * La regla de la clave (sin "nullable": null borra el valor).
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        return match ($this) {
            self::CONTACT_EMAIL => ['string', 'max:150', config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc'],
            self::CONTACT_PHONE => ['string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            self::WEBSITE => ['string', 'max:150', 'url:https'],
            self::DOCUMENT_FOOTER => ['string', 'max:500'],
            self::LOGO_PATH => ['prohibited'],
        };
    }

    /**
     * Las claves que se editan por la API, en el orden de la respuesta.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $key) => $key->isEditable()));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
