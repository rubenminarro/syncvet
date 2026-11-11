<?php

namespace Tests\Feature\Appointments\Transitions;

use App\Enums\AppointmentStatus;
use App\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Ausente (P12) y cancelar (P3), FASE 3, entrega 3.2b. El ausente lo marca la recepción a mano y
 * solo desde la hora de inicio del turno (antes, 422 APPOINTMENT_NO_SHOW_TOO_EARLY). Cancelar
 * pide el motivo (3 a 500) y no tiene límite de anticipación. El veterinario no marca ausentes ni
 * cancela (403).
 */
class AppointmentNoShowAndCancelTest extends TestCase
{
    use InteractsWithAppointments;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday('09:59:00');
        $this->schedule = $this->openSchedule($this->defaultBranchIn($this->createClinic()));
    }

    public function test_no_show_only_from_the_start_of_the_appointment(): void
    {
        $id = $this->appointmentIn($this->schedule, $this->today('10:00'));
        Sanctum::actingAs($this->userWithRoles($this->schedule->clinic, ['recepcionista']));

        $response = $this->transition($id, 'no-show');
        $this->assertErrorCode($response, 422, 'APPOINTMENT_NO_SHOW_TOO_EARLY');
        $response->assertJsonPath('message', 'El turno todavía no empezó: no se puede marcar como ausente.');
        $this->assertSame('confirmed', $this->appointmentRow($id)->status);

        // A la hora exacta de inicio, ya se puede.
        $this->travelTo($this->today('10:00'));
        $this->transition($id, 'no-show')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'no_show')
            ->assertJsonPath('data.no_show_at', '2026-11-10T13:00:00.000000Z');
    }

    public function test_a_past_day_appointment_can_be_marked_as_no_show(): void
    {
        $id = $this->appointmentIn($this->schedule, $this->today('10:00')->subDays(3));
        Sanctum::actingAs($this->userWithRoles($this->schedule->clinic, ['recepcionista']));

        $this->transition($id, 'no-show')->assertOk();
    }

    public function test_the_veterinarian_does_not_mark_no_show_nor_cancel(): void
    {
        $id = $this->appointmentIn($this->schedule, $this->today('08:00'));
        Sanctum::actingAs($this->userWithRoles($this->schedule->clinic, ['veterinario']));

        $this->assertErrorCode($this->transition($id, 'no-show'), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->transition($id, 'cancel', ['cancellation_reason' => 'No puede venir']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->transition($id, 'confirm'), 403, 'FORBIDDEN');
        $this->assertSame('confirmed', $this->appointmentRow($id)->status);
    }

    #[DataProvider('badReasons')]
    public function test_cancel_requires_a_reason(array $body, string $message): void
    {
        $id = $this->appointmentIn($this->schedule, $this->today('10:00'));
        Sanctum::actingAs($this->userWithRoles($this->schedule->clinic, ['recepcionista']));

        $this->transition($id, 'cancel', $body)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.cancellation_reason.0', $message);
        $this->assertSame('confirmed', $this->appointmentRow($id)->status);
    }

    public static function badReasons(): array
    {
        return [
            'sin motivo' => [[], 'Ingresá el motivo de la cancelación.'],
            'vacío' => [['cancellation_reason' => '   '], 'Ingresá el motivo de la cancelación.'],
            'corto' => [['cancellation_reason' => 'no'], 'El motivo de la cancelación debe tener al menos 3 caracteres.'],
            'largo' => [['cancellation_reason' => str_repeat('a', 501)], 'El motivo de la cancelación no debe tener más de 500 caracteres.'],
            'no es texto' => [['cancellation_reason' => ['x']], 'El motivo de la cancelación debe tener el formato correcto.'],
        ];
    }

    /**
     * Sin límite de anticipación: un turno de dentro de un minuto y uno de dentro de un año.
     */
    public function test_cancel_without_a_notice_limit(): void
    {
        $soon = $this->appointmentIn($this->schedule, $this->today('10:00'));
        $later = $this->appointmentIn($this->schedule, $this->today('10:00')->addYear(), AppointmentStatus::PENDING_CONFIRMATION);
        $user = $this->userWithRoles($this->schedule->clinic, ['recepcionista']);
        Sanctum::actingAs($user);

        $this->transition($soon, 'cancel', ['cancellation_reason' => 'Avisó tarde'])->assertOk();
        $this->transition($later, 'cancel', ['cancellation_reason' => str_repeat('a', 500)])
            ->assertOk()
            ->assertJsonPath('data.cancelled_by.id', $user->id);

        $row = $this->appointmentRow($later);
        $this->assertSame('cancelled', $row->status);
        $this->assertSame($user->id, (int) $row->cancelled_by);
        $this->assertSame('2026-11-10 09:59:00', $row->cancelled_at);
    }
}
