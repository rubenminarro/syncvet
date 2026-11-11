<?php

namespace Tests\Feature\Appointments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Sobreturno (FASE 3, entrega 3.2a; P2): overbook: true con el permiso appointments.overbook
 * (si no, 403); saltea solo la superposición, no el horario ni los bloqueos; is_overbooked
 * queda en true solo si al guardar se superponía.
 */
class AppointmentOverbookTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    public function test_an_overbooked_appointment_with_the_permission(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);
        $this->booked($schedule);

        $id = $this->book($schedule, ['overbook' => true, 'contact_name' => 'Sobreturno'])
            ->assertCreated()
            ->assertJsonPath('data.is_overbooked', true)
            ->json('data.id');

        $this->assertTrue((bool) $this->appointmentRow($id)->is_overbooked);
    }

    public function test_without_a_collision_it_is_not_marked(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);

        $this->book($schedule, ['overbook' => true])->assertCreated()->assertJsonPath('data.is_overbooked', false);
    }

    public function test_without_the_permission_it_is_forbidden(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);

        $this->assertErrorCode($this->book($schedule, ['overbook' => true]), 403, 'FORBIDDEN');
        $this->assertSame(0, DB::table('appointments')->count());

        // overbook: false no pide el permiso.
        $this->book($schedule, ['overbook' => false])->assertCreated();
    }

    public function test_the_reception_role_has_the_permission(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));
        $this->booked($schedule);

        $this->book($schedule, ['overbook' => true])->assertCreated()->assertJsonPath('data.is_overbooked', true);
    }

    public function test_an_overbooked_appointment_still_respects_the_hours_and_the_blocks(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->blockFor($schedule, [
            'starts_at' => CarbonImmutable::parse(self::MONDAY . ' 12:00', 'America/Asuncion'),
            'ends_at' => CarbonImmutable::parse(self::MONDAY . ' 13:00', 'America/Asuncion'),
        ]);
        // Revisión de la 3.2a (hallazgo 5): hay otro turno en cada horario, así que el sobreturno
        // se superpondría; igual respeta el horario y el bloqueo. Cargados sin la API: uno fuera
        // del horario y otro dentro del bloqueo.
        $this->appointmentIn($schedule, CarbonImmutable::parse(self::MONDAY . ' 20:00', 'America/Asuncion'));
        $this->appointmentIn($schedule, CarbonImmutable::parse(self::MONDAY . ' 12:30', 'America/Asuncion'));
        $this->actAsReception($clinic, overbook: true);

        $this->assertErrorCode($this->book($schedule, ['overbook' => true, 'starts_at' => $this->at('20:00')]), 422, 'APPOINTMENT_OUTSIDE_HOURS');
        $this->assertErrorCode($this->book($schedule, ['overbook' => true, 'starts_at' => $this->at('12:30')]), 422, 'APPOINTMENT_IN_BLOCK');
        $this->assertSame(2, DB::table('appointments')->count());

        // Dentro del horario y fuera del bloqueo, el mismo choque sí entra como sobreturno.
        $this->appointmentIn($schedule, CarbonImmutable::parse(self::MONDAY . ' 15:00', 'America/Asuncion'));
        $this->book($schedule, ['overbook' => true, 'starts_at' => $this->at('15:00')])
            ->assertCreated()->assertJsonPath('data.is_overbooked', true);
    }
}
