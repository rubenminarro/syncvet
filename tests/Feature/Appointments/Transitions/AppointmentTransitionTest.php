<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Estados del turno (FASE 3, entrega 3.2b): cada transición válida (estado, hora y auditoría
 * con el estado anterior y el nuevo), cada transición inválida de la tabla del diseño → 422
 * APPOINTMENT_INVALID_TRANSITION (también repetir la misma y reabrir un cancelado o un
 * atendido), y el horario que liberan el cancelado y el ausente.
 *
 * Reloj congelado el martes 10/11/2026 a las 11:00 de Asunción (14:00 UTC); el turno es de hoy
 * a las 10:00. El usuario tiene todos los permisos de turnos y appointments.update, así que la
 * regla de la ficha del veterinario no se aplica (la prueban AppointmentCheckInTest y
 * AppointmentAttendTest).
 */
class AppointmentTransitionTest extends TestCase
{
    use InteractsWithAppointments;

    private const NOW_UTC = '2026-11-10T14:00:00.000000Z';

    /**
     * Las transiciones que el diseño admite, por acción: la tabla "Estados del turno".
     * Escrita a mano (no sale de AppointmentTransition) para que un cambio en el enum la rompa.
     */
    private const ALLOWED = [
        'confirm' => ['pending_confirmation'],
        'check-in' => ['pending_confirmation', 'confirmed'],
        'attend' => ['checked_in'],
        'no-show' => ['pending_confirmation', 'confirmed'],
        'undo-no-show' => ['no_show'],
        'cancel' => ['pending_confirmation', 'confirmed', 'checked_in'],
    ];

    private Clinic $clinic;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('11:00:00');
        $this->clinic = $this->createClinic();
        $this->schedule = $this->openSchedule($this->defaultBranchIn($this->clinic));
        $this->actingIn($this->clinic, [...self::TRANSITION_PERMISSIONS, 'appointments.attend']);
    }

    public function test_confirm(): void
    {
        $id = $this->todayAppointment(AppointmentStatus::PENDING_CONFIRMATION);

        $this->transition($id, 'confirm')
            ->assertOk()
            ->assertJsonPath('message', 'Turno confirmado.')
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.confirmed_at', self::NOW_UTC);

        $this->assertStatusAudited($id, 'pending_confirmation', 'confirmed');
    }

    #[DataProvider('checkInOrigins')]
    public function test_check_in(AppointmentStatus $from): void
    {
        $id = $this->todayAppointment($from);

        $this->transition($id, 'check-in')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'checked_in')
            ->assertJsonPath('data.checked_in_at', self::NOW_UTC);

        $this->assertStatusAudited($id, $from->value, 'checked_in');
    }

    public static function checkInOrigins(): array
    {
        return ['a confirmar' => [AppointmentStatus::PENDING_CONFIRMATION], 'confirmado' => [AppointmentStatus::CONFIRMED]];
    }

    public function test_attend(): void
    {
        $id = $this->todayAppointment(AppointmentStatus::CHECKED_IN);

        $this->transition($id, 'attend')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'attended')
            ->assertJsonPath('data.attended_at', self::NOW_UTC);

        $this->assertSame('2026-11-10 11:00:00', $this->appointmentRow($id)->attended_at);
        $this->assertStatusAudited($id, 'checked_in', 'attended');
    }

    #[DataProvider('checkInOrigins')]
    public function test_no_show(AppointmentStatus $from): void
    {
        $id = $this->todayAppointment($from);

        $this->transition($id, 'no-show')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'no_show')
            ->assertJsonPath('data.no_show_at', self::NOW_UTC);

        $this->assertSame('2026-11-10 11:00:00', $this->appointmentRow($id)->no_show_at);
        $this->assertStatusAudited($id, $from->value, 'no_show');
    }

    #[DataProvider('cancelOrigins')]
    public function test_cancel(AppointmentStatus $from): void
    {
        $id = $this->todayAppointment($from);
        $userId = auth()->id();

        $this->transition($id, 'cancel', ['cancellation_reason' => 'El cliente avisó que no viene'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancelled_at', self::NOW_UTC)
            ->assertJsonPath('data.cancelled_by.id', $userId)
            ->assertJsonPath('data.cancellation_reason', 'El cliente avisó que no viene');

        $this->assertStatusAudited($id, $from->value, 'cancelled');
    }

    public static function cancelOrigins(): array
    {
        return [
            'a confirmar' => [AppointmentStatus::PENDING_CONFIRMATION],
            'confirmado' => [AppointmentStatus::CONFIRMED],
            'fichado' => [AppointmentStatus::CHECKED_IN],
        ];
    }

    /**
     * Toda acción desde un estado que la tabla no admite → 422, sin cambios: también repetir
     * la misma (confirmar un confirmado) y cualquier cambio de un atendido o un cancelado
     * (finales: un cancelado no se reabre).
     */
    #[DataProvider('invalidTransitions')]
    public function test_an_invalid_transition_is_rejected(string $action, AppointmentStatus $from): void
    {
        $id = $this->todayAppointment($from);
        $before = (array) $this->appointmentRow($id);

        $response = $this->transition($id, $action, ['cancellation_reason' => 'Motivo de prueba']);

        $this->assertErrorCode($response, 422, 'APPOINTMENT_INVALID_TRANSITION');
        $response->assertJsonPath('message', "El turno está «{$from->label()}» y no admite ese cambio.");
        $this->assertEquals($before, (array) $this->appointmentRow($id));
    }

    public static function invalidTransitions(): array
    {
        $cases = [];

        foreach (self::ALLOWED as $action => $allowed) {
            foreach (AppointmentStatus::cases() as $status) {
                if (! in_array($status->value, $allowed, true)) {
                    $cases["{$action} desde {$status->value}"] = [$action, $status];
                }
            }
        }

        return $cases;
    }

    /**
     * Un cancelado y un ausente liberan el horario (un alta en el mismo horario entra); un
     * fichado lo sigue ocupando.
     */
    public function test_cancelled_and_no_show_free_the_slot_and_checked_in_does_not(): void
    {
        $cancelled = $this->todayAppointment(AppointmentStatus::CONFIRMED, '12:00');
        $this->transition($cancelled, 'cancel', ['cancellation_reason' => 'Reprogramado'])->assertOk();
        $this->book($this->schedule, ['starts_at' => $this->at('12:00', self::TODAY)])->assertCreated();

        $absent = $this->todayAppointment(AppointmentStatus::CONFIRMED, '10:00');
        $this->transition($absent, 'no-show')->assertOk();
        $this->book($this->schedule, ['starts_at' => $this->at('10:00', self::TODAY)])->assertCreated();

        $checkedIn = $this->todayAppointment(AppointmentStatus::CONFIRMED, '13:00');
        $this->transition($checkedIn, 'check-in')->assertOk();
        $this->assertErrorCode($this->book($this->schedule, ['starts_at' => $this->at('13:00', self::TODAY)]), 409, 'APPOINTMENT_OVERLAP');
    }

    /**
     * Fichado → cancelado → fichado no se puede: el cancelado es final.
     */
    public function test_a_cancelled_appointment_is_not_reopened(): void
    {
        $id = $this->todayAppointment(AppointmentStatus::CONFIRMED);
        $this->transition($id, 'check-in')->assertOk();
        $this->transition($id, 'cancel', ['cancellation_reason' => 'Se fue antes de ser atendido'])->assertOk();

        foreach (['confirm', 'check-in', 'attend', 'no-show', 'undo-no-show', 'cancel'] as $action) {
            $this->assertErrorCode($this->transition($id, $action, ['cancellation_reason' => 'Otra vez']), 422, 'APPOINTMENT_INVALID_TRANSITION');
        }

        $this->assertSame('cancelled', $this->appointmentRow($id)->status);
    }

    private function todayAppointment(AppointmentStatus $status, string $time = '10:00'): int
    {
        return $this->appointmentIn($this->schedule, $this->today($time), $status);
    }

    private function assertStatusAudited(int $id, string $from, string $to): void
    {
        $log = DB::table('audit_logs')
            ->where('auditable_type', 'appointment')
            ->where('auditable_id', $id)
            ->where('event', 'updated')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'El cambio de estado no quedó en la auditoría.');
        $this->assertSame($from, json_decode($log->old_values, true)['status'] ?? null);
        $this->assertSame($to, json_decode($log->new_values, true)['status'] ?? null);
    }
}
