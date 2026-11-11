<?php

namespace Tests\Feature\Schedules;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Bloqueos de una agenda (FASE 3, entrega 3.1; preguntas 22 y 23): instantes con
 * AppDateTime (la base guarda la hora de Asunción y la API responde en UTC), en múltiplos de
 * 5 minutos, de hasta 366 días. Un bloqueo sobre turnos se permite y lista los afectados.
 */
class ScheduleBlockTest extends TestCase
{
    use InteractsWithSchedules;

    protected function setUp(): void
    {
        parent::setUp();

        // Martes 10/11/2026, 12:00 de Asunción (UTC-3).
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Asuncion'));
    }

    public function test_a_block_is_created_in_asuncion_time_and_answered_in_utc(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $user = $this->actAsScheduleManager($clinic);

        $response = $this->postJson("/api/schedules/{$schedule->id}/blocks", [
            'starts_at' => '2026-11-20T11:00:00Z',
            'ends_at' => '2026-11-20T15:30:00Z',
            'reason' => 'Capacitación',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Bloqueo creado correctamente.')
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.starts_at', '2026-11-20T11:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-11-20T15:30:00.000000Z')
            ->assertJsonPath('data.reason', 'Capacitación')
            ->assertJsonPath('data.created_by.id', $user->id)
            ->assertJsonPath('data.affected_appointments', []);

        $row = DB::table('schedule_blocks')->where('id', $response->json('data.id'))->first();
        $this->assertSame('2026-11-20 08:00:00', $row->starts_at);
        $this->assertSame('2026-11-20 12:30:00', $row->ends_at);
        $this->assertSame($user->id, (int) $row->created_by);
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule_block')->where('event', 'created')->count());
    }

    public function test_a_value_without_zone_is_asuncion_time(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $this->actAsScheduleManager($clinic);

        $this->postJson("/api/schedules/{$schedule->id}/blocks", ['starts_at' => '2026-11-20 08:00', 'ends_at' => '2026-11-20T12:00:00-03:00'])
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-11-20T11:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-11-20T15:00:00.000000Z');
    }

    public function test_the_response_lists_the_active_appointments_that_overlap(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $otherSchedule = $this->scheduleIn($this->branchOf($schedule), ['service_id' => $schedule->service_id]);
        $day = CarbonImmutable::parse('2026-11-20 00:00:00', 'America/Asuncion');

        $confirmed = $this->appointmentIn($schedule, $day->setTime(9, 0), AppointmentStatus::CONFIRMED);
        $pending = $this->appointmentIn($schedule, $day->setTime(11, 45), AppointmentStatus::PENDING_CONFIRMATION);
        $this->appointmentIn($schedule, $day->setTime(10, 0), AppointmentStatus::CANCELLED);
        $this->appointmentIn($schedule, $day->setTime(10, 0), AppointmentStatus::NO_SHOW);
        // Termina justo cuando empieza el bloqueo, y otro empieza justo cuando termina: solo se tocan.
        $this->appointmentIn($schedule, $day->setTime(8, 30), AppointmentStatus::CONFIRMED);
        $this->appointmentIn($schedule, $day->setTime(12, 0), AppointmentStatus::CONFIRMED);
        $this->appointmentIn($otherSchedule, $day->setTime(10, 0), AppointmentStatus::CONFIRMED);
        $this->actAsScheduleManager($clinic);

        $response = $this->postJson("/api/schedules/{$schedule->id}/blocks", [
            'starts_at' => '2026-11-20T09:00:00-03:00',
            'ends_at' => '2026-11-20T12:00:00-03:00',
        ])->assertCreated();

        $this->assertSame([$confirmed, $pending], array_column($response->json('data.affected_appointments'), 'id'));
        $response->assertJsonPath('data.affected_appointments.0.starts_at', '2026-11-20T12:00:00.000000Z')
            ->assertJsonPath('data.affected_appointments.0.status.value', 'confirmed')
            ->assertJsonStructure(['data' => ['affected_appointments' => [['id', 'starts_at', 'ends_at', 'status', 'patient' => ['id', 'code', 'name'], 'client' => ['id', 'display_name']]]]]);

        // Los turnos siguen como estaban.
        $this->assertSame(AppointmentStatus::CONFIRMED->value, DB::table('appointments')->where('id', $confirmed)->value('status'));
    }

    /**
     * Hallazgo 2 de la revisión: los cuatro estados que ocupan el horario
     * (AppointmentStatus::occupiesSlot), también fichado y atendido, son afectados.
     */
    public function test_checked_in_and_attended_appointments_are_also_affected(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $day = CarbonImmutable::parse('2026-11-20 00:00:00', 'America/Asuncion');
        $ids = [];

        foreach ([AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED, AppointmentStatus::CHECKED_IN, AppointmentStatus::ATTENDED] as $i => $status) {
            $ids[$status->value] = $this->appointmentIn($schedule, $day->setTime(9 + $i, 0), $status);
        }

        $this->actAsScheduleManager($clinic);

        $response = $this->postJson("/api/schedules/{$schedule->id}/blocks", [
            'starts_at' => '2026-11-20T08:00:00-03:00',
            'ends_at' => '2026-11-20T18:00:00-03:00',
        ])->assertCreated();

        $this->assertSame(array_values($ids), array_column($response->json('data.affected_appointments'), 'id'));
        $this->assertSame(
            ['pending_confirmation', 'confirmed', 'checked_in', 'attended'],
            array_column(array_column($response->json('data.affected_appointments'), 'status'), 'value')
        );
    }

    /**
     * Entrega 3.2a (desvío 11; revisión, hallazgo 6): un turno sin cliente ni paciente se muestra
     * con su nombre y su teléfono de contacto; uno con paciente, con los dos en null.
     */
    public function test_affected_appointments_show_the_contact(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $day = CarbonImmutable::parse('2026-11-20 00:00:00', 'America/Asuncion');
        $withPatient = $this->appointmentIn($schedule, $day->setTime(9, 0));
        $withContact = $this->appointmentIn($schedule, $day->setTime(10, 0));
        DB::table('appointments')->where('id', $withContact)->update([
            'patient_id' => null, 'client_id' => null, 'contact_name' => 'Juan, el de la perra Luna', 'contact_phone' => '981123456',
        ]);
        $this->actAsScheduleManager($clinic);

        $response = $this->postJson("/api/schedules/{$schedule->id}/blocks", [
            'starts_at' => '2026-11-20T08:00:00-03:00',
            'ends_at' => '2026-11-20T12:00:00-03:00',
        ])->assertCreated();

        $this->assertSame([$withPatient, $withContact], array_column($response->json('data.affected_appointments'), 'id'));
        $response->assertJsonPath('data.affected_appointments.0.contact_name', null)
            ->assertJsonPath('data.affected_appointments.0.contact_phone', null)
            ->assertJsonPath('data.affected_appointments.1.patient', null)
            ->assertJsonPath('data.affected_appointments.1.client', null)
            ->assertJsonPath('data.affected_appointments.1.contact_name', 'Juan, el de la perra Luna')
            ->assertJsonPath('data.affected_appointments.1.contact_phone', '981123456');
    }

    public function test_an_inactive_schedule_is_also_blocked_and_blocks_may_overlap(): void
    {
        [$clinic, $schedule] = $this->schedule(['is_active' => false]);
        $this->blockFor($schedule, ['starts_at' => '2026-11-20 08:00:00', 'ends_at' => '2026-11-20 12:00:00']);
        $this->actAsScheduleManager($clinic);

        $this->postJson("/api/schedules/{$schedule->id}/blocks", ['starts_at' => '2026-11-20T10:00:00-03:00', 'ends_at' => '2026-11-20T14:00:00-03:00'])
            ->assertCreated();
    }

    public static function invalidBlocks(): array
    {
        return [
            'fin igual al inicio' => [['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T10:00:00Z'], 'ends_at', 'El fin del bloqueo debe ser posterior a su inicio.'],
            'fin antes del inicio' => [['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T09:00:00Z'], 'ends_at', 'El fin del bloqueo debe ser posterior a su inicio.'],
            'más de 366 días' => [['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2027-11-21T10:05:00Z'], 'ends_at', 'El bloqueo no puede durar más de 366 días.'],
            'inicio fuera de la grilla' => [['starts_at' => '2026-11-20T10:02:00Z', 'ends_at' => '2026-11-20T11:00:00Z'], 'starts_at', 'La hora debe estar en múltiplos de 5 minutos, sin segundos.'],
            'fin con segundos' => [['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T11:00:30Z'], 'ends_at', 'La hora debe estar en múltiplos de 5 minutos, sin segundos.'],
            'una fecha sola' => [['starts_at' => '2026-11-20', 'ends_at' => '2026-11-21T10:00:00Z'], 'starts_at', 'Ingresá una fecha con hora (2026-11-09T13:00:00Z).'],
            'texto' => [['starts_at' => 'mañana', 'ends_at' => '2026-11-21T10:00:00Z'], 'starts_at', 'Ingresá una fecha con hora (2026-11-09T13:00:00Z).'],
            'sin fin' => [['starts_at' => '2026-11-20T10:00:00Z'], 'ends_at', 'El fin del bloqueo es obligatorio.'],
            'motivo largo' => [['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T11:00:00Z', 'reason' => str_repeat('a', 256)], 'reason', 'El motivo no debe tener más de 255 caracteres.'],
        ];
    }

    #[DataProvider('invalidBlocks')]
    public function test_invalid_blocks_are_rejected(array $payload, string $field, string $message): void
    {
        [$clinic, $schedule] = $this->schedule();
        $this->actAsScheduleManager($clinic);

        $this->postJson("/api/schedules/{$schedule->id}/blocks", $payload)
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}.0", $message);

        $this->assertSame(0, DB::table('schedule_blocks')->count());
    }

    public function test_exactly_366_days_pass(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $this->actAsScheduleManager($clinic);

        $this->postJson("/api/schedules/{$schedule->id}/blocks", ['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2027-11-21T10:00:00Z'])
            ->assertCreated();
    }

    public function test_a_block_is_deleted_with_its_audit(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $block = $this->blockFor($schedule);
        $this->actAsScheduleManager($clinic);

        $this->deleteJson("/api/schedule-blocks/{$block->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Bloqueo eliminado correctamente.');

        $this->assertSame(0, DB::table('schedule_blocks')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule_block')->where('auditable_id', $block->id)->where('event', 'deleted')->count());
    }

    public function test_a_schedule_of_another_clinic_in_the_url_is_not_found(): void
    {
        [, $foreign] = $this->schedule();
        $foreignBlock = $this->blockFor($foreign);
        [$clinic] = $this->schedule();
        $this->actAsScheduleManager($clinic);

        $this->getJson("/api/schedules/{$foreign->id}/blocks")->assertNotFound();
        $this->postJson("/api/schedules/{$foreign->id}/blocks", ['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T11:00:00Z'])->assertNotFound();
        $this->deleteJson("/api/schedule-blocks/{$foreignBlock->id}")->assertNotFound();
        $this->assertSame(1, DB::table('schedule_blocks')->count());
    }

    public function test_the_list_shows_by_default_the_blocks_that_end_from_today(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $this->blockFor($schedule, ['starts_at' => '2026-11-01 08:00:00', 'ends_at' => '2026-11-09 23:55:00', 'reason' => 'Pasado']);
        $today = $this->blockFor($schedule, ['starts_at' => '2026-11-09 08:00:00', 'ends_at' => '2026-11-10 08:00:00', 'reason' => 'Termina hoy']);
        $future = $this->blockFor($schedule, ['starts_at' => '2026-12-24 00:00:00', 'ends_at' => '2026-12-26 00:00:00', 'reason' => 'Navidad 100%']);
        $this->blockFor($this->scheduleIn($this->branchOf($schedule), ['service_id' => $schedule->service_id]), ['reason' => 'Otra agenda']);
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));

        $this->getJson("/api/schedules/{$schedule->id}/blocks")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.id', $today->id)
            ->assertJsonPath('data.1.id', $future->id);

        $this->getJson("/api/schedules/{$schedule->id}/blocks?from=2026-11-01&to=2026-11-05")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.reason', 'Pasado');

        $this->getJson("/api/schedules/{$schedule->id}/blocks?search=" . urlencode('100%'))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $future->id);

        $this->getJson("/api/schedules/{$schedule->id}/blocks?from=mañana")->assertUnprocessable()->assertJsonValidationErrors(['from']);
        $this->getJson("/api/schedules/{$schedule->id}/blocks?from=2026-11-05&to=2026-11-01")
            ->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'La fecha hasta no puede ser anterior a la fecha desde.');
    }

    public function test_the_detail_shows_the_upcoming_blocks(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $this->blockFor($schedule, ['starts_at' => '2026-11-01 08:00:00', 'ends_at' => '2026-11-02 08:00:00']);
        $upcoming = $this->blockFor($schedule, ['starts_at' => '2026-11-20 08:00:00', 'ends_at' => '2026-11-20 12:00:00']);
        $this->actAsScheduleManager($clinic);

        $this->getJson("/api/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.upcoming_blocks')
            ->assertJsonPath('data.upcoming_blocks.0.id', $upcoming->id)
            ->assertJsonPath('data.upcoming_blocks.0.starts_at', '2026-11-20T11:00:00.000000Z');
    }

    public function test_the_reception_and_the_veterinarian_cannot_create_or_delete_blocks(): void
    {
        [$clinic, $schedule] = $this->schedule();
        $block = $this->blockFor($schedule);

        foreach (['recepcionista', 'veterinario'] as $role) {
            Sanctum::actingAs($this->userWithRoles($clinic, [$role]));

            $this->postJson("/api/schedules/{$schedule->id}/blocks", ['starts_at' => '2026-11-20T10:00:00Z', 'ends_at' => '2026-11-20T11:00:00Z'])->assertForbidden();
            $this->deleteJson("/api/schedule-blocks/{$block->id}")->assertForbidden();
            $this->getJson("/api/schedules/{$schedule->id}/blocks")->assertOk();
        }
    }

    /**
     * @return array{0: \App\Models\Clinic, 1: \App\Models\Schedule}
     */
    private function schedule(array $attributes = []): array
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);

        return [$clinic, $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id, ...$attributes])];
    }
}
