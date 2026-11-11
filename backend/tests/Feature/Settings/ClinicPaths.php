<?php

namespace Tests\Feature\Settings;

/**
 * Rutas de ejemplo con el nombre que les da ClinicLogoService (FASE 2, entrega 2.5).
 */
final class ClinicPaths
{
    public static function logo(int $clinicId): string
    {
        return "clinics/{$clinicId}/logo/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.png";
    }
}
