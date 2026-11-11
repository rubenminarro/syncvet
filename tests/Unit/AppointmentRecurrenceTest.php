<?php

namespace Tests\Unit;

use App\Support\AppointmentRecurrence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Las fechas de una serie de turnos (FASE 3, entrega 3.3): el paso es de semanas (7 ×
 * every_weeks días) y conserva la hora de reloj de Asunción aunque cambie el desplazamiento.
 * Paraguay es UTC-3 todo el año desde el 15/10/2024; antes, el horario de verano empezaba el
 * primer domingo de octubre (el 01/10/2023: de UTC-4 a UTC-3).
 */
class AppointmentRecurrenceTest extends TestCase
{
    public function test_the_step_is_in_weeks(): void
    {
        $first = CarbonImmutable::parse('2026-11-16 10:00', 'America/Asuncion');

        $this->assertSame('2026-11-16 10:00', AppointmentRecurrence::shift($first, 1, 0)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 10:00', AppointmentRecurrence::shift($first, 1, 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-12-14 10:00', AppointmentRecurrence::shift($first, 2, 2)->format('Y-m-d H:i'));
    }

    public function test_the_clock_time_stays_when_the_offset_changes(): void
    {
        $first = CarbonImmutable::parse('2023-09-25 10:00', 'America/Asuncion');
        $next = AppointmentRecurrence::shift($first, 1, 1);

        $this->assertSame('2023-10-02 10:00', $next->format('Y-m-d H:i'));
        $this->assertSame('2023-09-25T14:00:00.000000Z', $first->toISOString());
        $this->assertSame('2023-10-02T13:00:00.000000Z', $next->toISOString());
    }

    public function test_the_count_until_a_date_includes_it(): void
    {
        $first = CarbonImmutable::parse('2026-11-16 10:00', 'America/Asuncion');

        $this->assertSame(4, AppointmentRecurrence::count($first, 1, 4, null));
        $this->assertSame(4, AppointmentRecurrence::count($first, 2, null, '2026-12-28'));
        $this->assertSame(3, AppointmentRecurrence::count($first, 2, null, '2026-12-27'));
        $this->assertSame(1, AppointmentRecurrence::count($first, 1, null, '2026-11-16'));
        $this->assertSame(0, AppointmentRecurrence::count($first, 1, null, '2026-11-15'));
        $this->assertSame(53, AppointmentRecurrence::count($first, 1, null, '2027-11-15'));

        // Cruzando el cambio de hora de 2023, la cuenta sigue en días enteros.
        $old = CarbonImmutable::parse('2023-09-25 10:00', 'America/Asuncion');
        $this->assertSame(2, AppointmentRecurrence::count($old, 1, null, '2023-10-02'));
    }
}
