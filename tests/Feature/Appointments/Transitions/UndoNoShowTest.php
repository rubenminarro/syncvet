<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Deshacer el ausente (FASE 3, entrega 3.2b; pregunta 19): el mismo día del turno en Asunción,
 * por el administrador y la recepción (appointments.no-show). Vuelve a confirmado, con
 * no_show_at en null y confirmed_at, la hora actual si no tenía. Vuelve a ocupar el horario: si
 * otro turno lo tomó (en la agenda o del mismo veterinario en otra agenda), 409
 * APPOINTMENT_OVERLAP, salvo un sobreturno, que entra y recalcula is_overbooked. Atendido y
 * cancelado no se reabren por acá.
 */
class UndoNoShowTest extends TestCase
{
    use InteractsWithAppointments;

    private Clinic $clinic;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('11:00:00');
        $this->clinic = $this->createClinic();
        $this->schedule = $this->openSchedule($this->defaultBranchIn($this->clinic));
        Sanctum::actingAs($this->userWithRoles($this->clinic, ['recepcionista']));
    }

    public function test_the_same_day_it_goes_back_to_confirmed(): void
    {
        $id = $this->absent('10:00', ['confirmed_at' => null]);

        $this->transition($id, 'undo-no-show')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.no_show_at', null)
            ->assertJsonPath('data.confirmed_at', '2026-11-10T14:00:00.000000Z')
            ->assertJsonPath('data.is_overbooked', false);

        $log = DB::table('audit_logs')->where('auditable_type', 'appointment')->where('auditable_id', $id)->where('event', 'updated')->orderByDesc('id')->first();
        $this->assertSame('no_show', json_decode($log->old_values, true)['status']);
        $this->assertSame('confirmed', json_decode($log->new_values, true)['status']);
    }

    public function test_an_existing_confirmation_time_is_kept(): void
    {
        $id = $this->absent('10:00', ['confirmed_at' => '2026-11-09 18:00:00']);

        $this->transition($id, 'undo-no-show')->assertOk()->assertJsonPath('data.confirmed_at', '2026-11-09T21:00:00.000000Z');
    }

    public function test_another_day_it_is_not_undoable(): void
    {
        $id = $this->absent('10:00');
        $this->travelTo($this->today('00:05')->addDay());

        $response = $this->transition($id, 'undo-no-show');

        $this->assertErrorCode($response, 422, 'APPOINTMENT_NO_SHOW_NOT_UNDOABLE');
        $response->assertJsonPath('message', 'El ausente solo se deshace el mismo día del turno.');
        $this->assertSame('no_show', $this->appointmentRow($id)->status);
    }

    public function test_an_appointment_that_is_not_absent_is_rejected(): void
    {
        $id = $this->appointmentIn($this->schedule, $this->today('10:00'));

        $this->assertErrorCode($this->transition($id, 'undo-no-show'), 422, 'APPOINTMENT_INVALID_TRANSITION');
    }

    /**
     * El horario lo tomó otro turno mientras estaba ausente: 409 y sigue ausente. También uno
     * del mismo veterinario en otra agenda (pregunta 15).
     */
    public function test_a_taken_slot_is_rejected(): void
    {
        $id = $this->absent('10:00');
        $this->book($this->schedule, ['starts_at' => $this->at('10:15', self::TODAY)])->assertCreated();

        $response = $this->transition($id, 'undo-no-show');

        $this->assertErrorCode($response, 409, 'APPOINTMENT_OVERLAP');
        $this->assertSame('no_show', $this->appointmentRow($id)->status);

        // Que se toquen no choca: de 09:45 a 10:15, y el otro empieza a las 10:15.
        $touching = $this->absent('09:45');
        $this->transition($touching, 'undo-no-show')->assertOk();
    }

    public function test_the_same_veterinarian_in_another_schedule_takes_the_slot(): void
    {
        $id = $this->absent('10:00');
        $other = $this->scheduleIn($this->branchOf($this->schedule), ['veterinarian_id' => $this->schedule->veterinarian_id]);
        $this->appointmentIn($other, $this->today('10:00'));

        $this->assertErrorCode($this->transition($id, 'undo-no-show'), 409, 'APPOINTMENT_OVERLAP');
    }

    /**
     * Un sobreturno vuelve aunque el horario esté tomado, y sigue marcado; si quedó libre, deja de
     * serlo.
     */
    public function test_an_overbooked_appointment_comes_back_and_recalculates_its_mark(): void
    {
        $taken = $this->absent('10:00', ['is_overbooked' => true]);
        $this->appointmentIn($this->schedule, $this->today('10:00'));
        $this->transition($taken, 'undo-no-show')->assertOk()->assertJsonPath('data.is_overbooked', true);

        $free = $this->absent('12:00', ['is_overbooked' => true]);
        $this->transition($free, 'undo-no-show')->assertOk()->assertJsonPath('data.is_overbooked', false);
    }

    public function test_the_veterinarian_cannot_undo(): void
    {
        $id = $this->absent('10:00');
        Sanctum::actingAs($this->userWithRoles($this->clinic, ['veterinario']));

        $this->assertErrorCode($this->transition($id, 'undo-no-show'), 403, 'FORBIDDEN');
        $this->assertSame('no_show', $this->appointmentRow($id)->status);
    }

    private function absent(string $time, array $attributes = []): int
    {
        $id = $this->appointmentIn($this->schedule, $this->today($time));
        $this->setStatus($id, AppointmentStatus::NO_SHOW, ['no_show_at' => self::TODAY . " {$time}:00", ...$attributes]);

        return $id;
    }
}
