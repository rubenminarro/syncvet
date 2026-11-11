<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Superposición (FASE 3, entrega 3.2a; decisiones comunes y pregunta 15): dos turnos se
 * superponen si a.starts_at < b.ends_at y a.ends_at > b.starts_at; ocupan el horario los a
 * confirmar, confirmados, fichados y atendidos. En la misma agenda y con el mismo veterinario
 * en otra agenda → 409 APPOINTMENT_OVERLAP.
 */
class AppointmentOverlapTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function monday(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::MONDAY . " {$time}", 'America/Asuncion');
    }

    public function test_the_same_slot_is_taken(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $this->booked($schedule);

        $response = $this->book($schedule, ['contact_name' => 'Otra persona']);

        $this->assertErrorCode($response, 409, 'APPOINTMENT_OVERLAP');
        $response->assertJsonPath('message', 'El horario ya está ocupado: elegí otro o dalo como sobreturno.');
        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_partial_overlaps_are_taken_and_touching_ones_are_not(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $this->booked($schedule, ['starts_at' => $this->at('10:00'), 'ends_at' => $this->at('11:00')]);

        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('09:30'), 'ends_at' => $this->at('10:05')]), 409, 'APPOINTMENT_OVERLAP');
        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('10:55'), 'ends_at' => $this->at('11:30')]), 409, 'APPOINTMENT_OVERLAP');
        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('10:15'), 'ends_at' => $this->at('10:30')]), 409, 'APPOINTMENT_OVERLAP');
        $this->assertErrorCode($this->book($schedule, ['starts_at' => $this->at('09:00'), 'ends_at' => $this->at('12:00')]), 409, 'APPOINTMENT_OVERLAP');

        // Uno que termina a las 10:00 y otro que empieza a las 11:00 no chocan.
        $this->book($schedule, ['starts_at' => $this->at('09:30'), 'ends_at' => $this->at('10:00')])->assertCreated();
        $this->book($schedule, ['starts_at' => $this->at('11:00'), 'ends_at' => $this->at('11:30')])->assertCreated();
    }

    public static function statuses(): array
    {
        return [
            'a confirmar' => [AppointmentStatus::PENDING_CONFIRMATION, true],
            'confirmado' => [AppointmentStatus::CONFIRMED, true],
            'fichado' => [AppointmentStatus::CHECKED_IN, true],
            'atendido' => [AppointmentStatus::ATTENDED, true],
            'ausente' => [AppointmentStatus::NO_SHOW, false],
            'cancelado' => [AppointmentStatus::CANCELLED, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_occupying_statuses_take_the_slot(AppointmentStatus $status, bool $occupies): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->appointmentIn($schedule, $this->monday('10:00'), $status);
        $this->actAsReception($clinic);

        $response = $this->book($schedule);

        $occupies ? $this->assertErrorCode($response, 409, 'APPOINTMENT_OVERLAP') : $response->assertCreated();
    }

    /**
     * Pregunta 15: el mismo veterinario en otra agenda (también de otra sucursal).
     */
    public function test_the_same_veterinarian_in_another_schedule_is_taken(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $veterinarian = $this->veterinarianIn($branch, $other);
        $first = $this->openSchedule($branch, ['veterinarian_id' => $veterinarian->id]);
        $second = $this->openSchedule($other, ['veterinarian_id' => $veterinarian->id]);
        $this->actAsReception($clinic, overbook: true);
        $this->booked($first);

        $this->assertErrorCode($this->book($second), 409, 'APPOINTMENT_OVERLAP');

        $this->book($second, ['overbook' => true])->assertCreated()->assertJsonPath('data.is_overbooked', true);
    }

    public function test_another_veterinarian_or_a_schedule_without_one_is_free(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        $first = $this->openSchedule($branch);
        $second = $this->openSchedule($branch);
        $grooming = $this->openSchedule($branch, ['veterinarian_id' => null, 'service_id' => $service->id]);
        $bathing = $this->openSchedule($branch, ['veterinarian_id' => null, 'service_id' => $service->id]);
        $this->actAsReception($clinic);

        $this->booked($first);
        $this->booked($second);
        $this->booked($grooming);
        $this->booked($bathing);

        $this->assertSame(4, DB::table('appointments')->count());
    }

    public function test_a_common_appointment_does_not_overlap_an_overbooked_one(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic, overbook: true);
        $first = $this->booked($schedule);
        $this->book($schedule, ['overbook' => true])->assertCreated();

        // Cancelado el primero, queda el sobreturno, que también ocupa el horario.
        DB::table('appointments')->where('id', $first)->update(['status' => AppointmentStatus::CANCELLED->value]);

        $this->assertErrorCode($this->book($schedule), 409, 'APPOINTMENT_OVERLAP');
    }
}
