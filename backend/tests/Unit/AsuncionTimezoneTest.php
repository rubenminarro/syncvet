<?php

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Zona horaria de Paraguay (FASE 2, entrega 2.6; deuda 13): desde el 15/10/2024,
 * America/Asuncion es UTC-3 todo el año, sin horario de verano (ley promulgada el
 * 14/10/2024). La base de zonas de la IANA lo incluye desde la versión 2025a.
 *
 * AppDateTime y ApiDateTime convierten con la zona America/Asuncion de PHP: con una base
 * anterior (PHP 8.3.16 trae la 2024.2), de marzo a octubre PHP sigue usando -04:00 y toda
 * fecha con hora queda una hora corrida. Este test falla en ese caso.
 *
 * No se compara timezone_version_get(): con la base del sistema (paquetes de Linux)
 * devuelve "0.system". Se comprueba el desplazamiento, que es lo que importa.
 */
class AsuncionTimezoneTest extends TestCase
{
    public static function dates(): array
    {
        return [
            'invierno' => ['2026-07-01 12:00:00'],
            'verano' => ['2026-01-15 12:00:00'],
        ];
    }

    #[DataProvider('dates')]
    public function test_asuncion_is_utc_minus_three_all_year(string $date): void
    {
        $this->assertSame('America/Asuncion', config('app.timezone'));

        $offset = (new DateTimeImmutable($date, new DateTimeZone('America/Asuncion')))->format('P');

        $this->assertSame('-03:00', $offset, sprintf(
            'America/Asuncion da %s el %s: la base de zonas de PHP (%s) es anterior a la 2025a y todavía tiene el horario de '
            . 'verano de Paraguay. Actualizá PHP o instalá la extensión timezonedb (docs/04-desarrollo/setup.md).',
            $offset,
            $date,
            timezone_version_get()
        ));
    }
}
