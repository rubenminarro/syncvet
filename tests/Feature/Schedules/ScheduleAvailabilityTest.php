<?php

namespace Tests\Feature\Schedules;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Http\Requests\AppointmentMessages;
use App\Http\Requests\ScheduleAvailabilityRequest;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Disponibilidad de una agenda (FASE 3, entrega 3.4): los horarios cada slot_minutes desde el
 * comienzo de cada tramo, con la duración del servicio (el del pedido, el de la agenda o
 * slot_minutes), sin los que tocan un bloqueo, se superponen con un turno que ocupa el horario
 * (en la agenda o del mismo veterinario en otra) o ya empezaron. Las mismas reglas que el alta:
 * todo horario ofrecido se da (201) y todo candidato no ofrecido se rechaza (409 o 422).
 *
 * El reloj, el martes 10/11/2026 a las 07:00 de Asunción; MONDAY es el lunes 16/11.
 */
class ScheduleAvailabilityTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function actAsReceptionWithAvailability(Clinic $clinic): User
    {
        return $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, 'schedules.availability']);
    }

    private function availability(Schedule $schedule, string $query = ''): TestResponse
    {
        return $this->getJson("/api/schedules/{$schedule->id}/availability" . ($query === '' ? '' : "?{$query}"));
    }

    /**
     * Las horas de inicio (hora de Asunción, "H:i") que ofrece la agenda el día.
     *
     * @return list<string>
     */
    private function starts(Schedule $schedule, string $date, string $query = ''): array
    {
        $response = $this->availability($schedule, "from={$date}" . ($query === '' ? '' : "&{$query}"))->assertOk();

        return array_map(
            fn (string $iso) => CarbonImmutable::parse($iso)->setTimezone('America/Asuncion')->format('H:i'),
            $response->json('data.days.0.slots.*.starts_at')
        );
    }

    /**
     * Agenda de un veterinario de la sucursal, sin servicio, con los tramos del día.
     *
     * @param list<array{0: string, 1: string}> $ranges
     */
    private function scheduleWith(Clinic $clinic, array $ranges, int $weekday = 1, array $attributes = []): Schedule
    {
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch)->id, ...$attributes]);
        $this->hoursFor($schedule, array_map(fn (array $range) => [$weekday, $range[0], $range[1]], $ranges));

        return $schedule;
    }

    private function monday(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::MONDAY . " {$time}", 'America/Asuncion');
    }

    public function test_the_slots_of_a_day_with_two_ranges_in_utc(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '10:00'], ['14:00', '15:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        $response = $this->availability($schedule, 'from=' . self::MONDAY)->assertOk()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonCount(1, 'data.days')
            ->assertJsonPath('data.days.0.date', self::MONDAY);

        // 08:00 en Asunción son las 11:00 UTC.
        $this->assertSame('2026-11-16T11:00:00.000000Z', $response->json('data.days.0.slots.0.starts_at'));
        $this->assertSame('2026-11-16T11:30:00.000000Z', $response->json('data.days.0.slots.0.ends_at'));
        $this->assertSame(['08:00', '08:30', '09:00', '09:30', '14:00', '14:30'], $this->starts($schedule, self::MONDAY));
    }

    public function test_occupied_slots_and_blocks_are_not_offered_but_cancelled_and_absent_ones_are(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '12:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        $this->blockFor($schedule, ['starts_at' => $this->monday('08:30'), 'ends_at' => $this->monday('09:00')]);
        $this->appointmentIn($schedule, $this->monday('09:00'), AppointmentStatus::CONFIRMED);
        $this->appointmentIn($schedule, $this->monday('09:30'), AppointmentStatus::CHECKED_IN);
        $this->appointmentIn($schedule, $this->monday('10:00'), AppointmentStatus::CANCELLED);
        $this->appointmentIn($schedule, $this->monday('10:30'), AppointmentStatus::NO_SHOW);
        $overbooked = $this->appointmentIn($schedule, $this->monday('11:00'), AppointmentStatus::PENDING_CONFIRMATION);
        DB::table('appointments')->where('id', $overbooked)->update(['is_overbooked' => true]);

        $this->assertSame(['08:00', '10:00', '10:30', '11:30'], $this->starts($schedule, self::MONDAY));
    }

    public function test_the_veterinarian_busy_in_another_schedule_is_not_offered(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '10:00']]);
        $other = $this->scheduleIn($this->branchOf($schedule), ['veterinarian_id' => $schedule->veterinarian_id]);
        $this->hoursFor($other, [[1, '08:00', '12:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        // 08:45 a 09:15 en la otra agenda: ocupa 08:30 y 09:00.
        $this->appointmentIn($other, $this->monday('08:45'), AppointmentStatus::CONFIRMED);

        $this->assertSame(['08:00', '09:30'], $this->starts($schedule, self::MONDAY));
    }

    public function test_ranges_that_touch_count_as_one_and_a_gap_does_not(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['duration_minutes' => 60]);
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00'], ['09:00', '10:00'], ['10:30', '11:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        // 08:30 cruza el límite de los tramos que se tocan; en 10:30–11:00 no entra una hora.
        $this->assertSame(['08:00', '08:30', '09:00'], $this->starts($schedule, self::MONDAY, "service_id={$service->id}"));
    }

    public function test_the_duration_of_the_requested_or_the_schedule_service(): void
    {
        $clinic = $this->createClinic();
        $long = $this->serviceIn($clinic, ['name' => 'Cirugía menor', 'duration_minutes' => 45]);
        $own = $this->serviceIn($clinic, ['name' => 'Control', 'duration_minutes' => 60]);
        $schedule = $this->scheduleWith($clinic, [['08:00', '10:00']], 1, ['service_id' => $own->id]);
        $this->actAsReceptionWithAvailability($clinic);

        // El servicio de la agenda: una hora.
        $this->assertSame(['08:00', '08:30', '09:00'], $this->starts($schedule, self::MONDAY));
        $this->availability($schedule, 'from=' . self::MONDAY)->assertJsonPath('data.duration_minutes', 60);

        // El del pedido: 45 minutos; 09:30 + 45 no entra al final del tramo.
        $this->assertSame(['08:00', '08:30', '09:00'], $this->starts($schedule, self::MONDAY, "service_id={$long->id}"));
        $this->availability($schedule, 'from=' . self::MONDAY . "&service_id={$long->id}")
            ->assertJsonPath('data.duration_minutes', 45)
            ->assertJsonPath('data.days.0.slots.2.ends_at', '2026-11-16T12:45:00.000000Z');
    }

    public function test_today_the_slots_that_already_started_are_not_offered(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '11:00']], 2);
        $this->actAsReceptionWithAvailability($clinic);

        $this->freezeToday('09:10:00');
        // Sin from: hoy en Asunción.
        $this->availability($schedule)->assertOk()->assertJsonPath('data.days.0.date', self::TODAY);
        $this->assertSame(['09:30', '10:00', '10:30'], $this->starts($schedule, self::TODAY));

        // A las 09:30 en punto, el de las 09:30 todavía no empezó.
        $this->freezeToday('09:30:00');
        $this->assertSame(['09:30', '10:00', '10:30'], $this->starts($schedule, self::TODAY));

        $this->freezeToday('09:30:01');
        $this->assertSame(['10:00', '10:30'], $this->starts($schedule, self::TODAY));
    }

    public function test_near_midnight_today_is_the_day_of_asuncion(): void
    {
        $clinic = $this->createClinic();
        // Martes de 23:00 a 23:55 y miércoles de 08:00 a 09:00, cada 5 minutos.
        $schedule = $this->scheduleWith($clinic, [['23:00', '23:55']], 2, ['slot_minutes' => 5]);
        $this->hoursFor($schedule, [[3, '08:00', '09:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        // 23:40 del martes en Asunción: en UTC ya es miércoles (02:40Z).
        $this->freezeToday('23:40:00');
        $this->assertSame('2026-11-11', now('UTC')->toDateString());

        $response = $this->availability($schedule)->assertOk()->assertJsonPath('data.days.0.date', self::TODAY);
        $this->assertSame(['23:40', '23:45', '23:50'], $this->starts($schedule, self::TODAY));
        $this->assertSame('2026-11-11T02:50:00.000000Z', $response->json('data.days.0.slots.2.starts_at'));
        $this->assertSame('2026-11-11T02:55:00.000000Z', $response->json('data.days.0.slots.2.ends_at'));
    }

    public function test_several_days_a_day_without_ranges_and_a_past_day(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        // Del lunes 16 al domingo 22: solo el lunes tiene horario.
        $response = $this->availability($schedule, 'from=' . self::MONDAY . '&to=2026-11-22')->assertOk()->assertJsonCount(7, 'data.days');
        $this->assertSame(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22'], $response->json('data.days.*.date'));
        $this->assertCount(2, $response->json('data.days.0.slots'));
        $this->assertSame([[], [], [], [], [], []], array_slice($response->json('data.days.*.slots'), 1));

        // El lunes pasado (09/11): el alta lo rechaza, así que no se ofrece nada.
        $this->assertSame([], $this->starts($schedule, '2026-11-09'));
    }

    public function test_the_range_is_validated(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        $this->availability($schedule, 'from=2026-11-16&to=2026-11-23')
            ->assertUnprocessable()->assertJsonPath('errors.to.0', ScheduleAvailabilityRequest::RANGE_TOO_LONG);
        $this->availability($schedule, 'from=2026-11-16&to=2026-11-15')->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->availability($schedule, 'from=2026-11-16T10:00:00Z')
            ->assertUnprocessable()->assertJsonPath('errors.from.0', ScheduleAvailabilityRequest::DATE_FORMAT);
        $this->availability($schedule, 'from=mañana&service_id=999999')->assertUnprocessable()->assertJsonValidationErrors(['from', 'service_id']);
        // Sin from, hasta 7 días desde hoy.
        $this->availability($schedule, 'to=2026-11-16')->assertOk()->assertJsonCount(7, 'data.days');
        $this->availability($schedule, 'to=2026-11-17')->assertUnprocessable()->assertJsonValidationErrors(['to']);
    }

    public function test_an_inactive_schedule_veterinarian_or_service_is_rejected_like_the_booking(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        DB::table('schedules')->where('id', $schedule->id)->update(['is_active' => false]);
        $this->availability($schedule, 'from=' . self::MONDAY)
            ->assertUnprocessable()->assertJsonPath('errors.schedule.0', AppointmentMessages::SCHEDULE_NOT_AVAILABLE);

        DB::table('schedules')->where('id', $schedule->id)->update(['is_active' => true]);
        DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->update(['status' => VeterinarianStatus::INACTIVE->value]);
        $this->availability($schedule, 'from=' . self::MONDAY)
            ->assertUnprocessable()->assertJsonPath('errors.schedule.0', AppointmentMessages::VETERINARIAN_INACTIVE);

        DB::table('veterinarians')->where('id', $schedule->veterinarian_id)->update(['status' => VeterinarianStatus::ACTIVE->value]);
        $service = $this->serviceIn($clinic, ['status' => ServiceStatus::INACTIVE]);
        $this->availability($schedule, 'from=' . self::MONDAY . "&service_id={$service->id}")
            ->assertUnprocessable()->assertJsonPath('errors.service_id.0', AppointmentMessages::SERVICE_NOT_AVAILABLE);

        // El servicio inactivo de la agenda también: el alta sin service_id lo rechazaría.
        DB::table('schedules')->where('id', $schedule->id)->update(['service_id' => $service->id]);
        $this->availability($schedule, 'from=' . self::MONDAY)->assertUnprocessable()->assertJsonValidationErrors(['service_id']);
    }

    public function test_a_schedule_or_service_of_another_clinic_is_not_found(): void
    {
        $foreign = $this->createClinic();
        $foreignSchedule = $this->scheduleWith($foreign, [['08:00', '09:00']]);
        $foreignService = $this->serviceIn($foreign);

        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00']]);
        $this->actAsReceptionWithAvailability($clinic);

        $this->assertErrorCode($this->availability($foreignSchedule, 'from=' . self::MONDAY), 404, 'RESOURCE_NOT_FOUND');
        $this->availability($schedule, 'from=' . self::MONDAY . "&service_id={$foreignService->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['service_id']);
    }

    public function test_without_the_permission_403(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '09:00']]);
        $this->actingIn($clinic, ['schedules.index', 'schedules.show']);

        $this->assertErrorCode($this->availability($schedule), 403, 'FORBIDDEN');
    }


    /**
     * D2: duration_minutes, de 5 a 720 en múltiplos de 5, manda sobre la duración del servicio
     * (el pedido o el de la agenda) y slot_minutes, y sale en data.duration_minutes. El servicio
     * pedido se sigue revisando.
     */
    public function test_the_requested_duration_wins_and_is_validated(): void
    {
        $clinic = $this->createClinic();
        $own = $this->serviceIn($clinic, ['name' => 'Control', 'duration_minutes' => 60]);
        $schedule = $this->scheduleWith($clinic, [['08:00', '10:00']], 1, ['service_id' => $own->id]);
        $this->actAsReceptionWithAvailability($clinic);

        // Sin duration_minutes, la del servicio de la agenda (60); con 45, cada 30 minutos.
        $this->assertSame(['08:00', '08:30', '09:00'], $this->starts($schedule, self::MONDAY));
        $this->assertSame(['08:00', '08:30', '09:00'], $this->starts($schedule, self::MONDAY, 'duration_minutes=45'));
        $this->availability($schedule, 'from=' . self::MONDAY . '&duration_minutes=45')
            ->assertJsonPath('data.duration_minutes', 45)
            ->assertJsonPath('data.days.0.slots.2.ends_at', '2026-11-16T12:45:00.000000Z');
        $this->assertSame(['08:00', '08:30', '09:00', '09:30'], $this->starts($schedule, self::MONDAY, 'duration_minutes=5'));

        // Los bordes: 5 y 720 pasan (720 no entra en un tramo de 2 horas); 4, 7 y 725, no.
        $this->availability($schedule, 'from=' . self::MONDAY . '&duration_minutes=5')->assertOk()->assertJsonPath('data.duration_minutes', 5);
        $this->availability($schedule, 'from=' . self::MONDAY . '&duration_minutes=720')->assertOk()
            ->assertJsonPath('data.duration_minutes', 720)->assertJsonPath('data.days.0.slots', []);

        foreach (['4', '7', '725', '30.5', 'media'] as $invalid) {
            $this->availability($schedule, 'from=' . self::MONDAY . "&duration_minutes={$invalid}")
                ->assertUnprocessable()
                ->assertJsonPath('errors.duration_minutes.0', ScheduleAvailabilityRequest::DURATION);
        }

        // Con un servicio inactivo, la duración propia no lo saltea.
        $inactive = $this->serviceIn($clinic, ['name' => 'Baño', 'status' => ServiceStatus::INACTIVE]);
        $this->availability($schedule, 'from=' . self::MONDAY . "&service_id={$inactive->id}&duration_minutes=30")
            ->assertUnprocessable()->assertJsonPath('errors.service_id.0', AppointmentMessages::SERVICE_NOT_AVAILABLE);
    }

    /**
     * Desvío 7 aceptado: hoy, un horario que ya empezó no se ofrece, pero el alta lo acepta (la
     * pregunta 16 solo rechaza los días anteriores a hoy). La garantía "todo candidato no
     * ofrecido se rechaza" vale desde ahora; antes de ahora, la disponibilidad es más estricta.
     */
    public function test_today_a_slot_that_already_started_is_not_offered_but_the_booking_accepts_it(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [['08:00', '11:00']], 2);
        $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, 'schedules.availability']);
        $this->freezeToday('09:10:00');

        $this->assertNotContains('09:00', $this->starts($schedule, self::TODAY));
        $this->book($schedule, ['starts_at' => $this->at('09:00', self::TODAY)])->assertCreated();
    }

    /**
     * H11: la disponibilidad de una agenda de otra sucursal (no la actual del usuario) se
     * consulta, como el alta da turnos en cualquier agenda de la clínica.
     */
    public function test_a_schedule_of_another_branch_than_the_current_one_is_available(): void
    {
        $clinic = $this->createClinic();
        $current = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($other, ['veterinarian_id' => $this->veterinarianIn($other)->id]);
        $this->hoursFor($schedule, [[1, '08:00', '09:00']]);
        $user = $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, 'schedules.availability']);
        DB::table('users')->where('id', $user->id)->update(['current_branch_id' => $current->id]);
        Sanctum::actingAs($user->fresh());

        $this->assertSame(['08:00', '08:30'], $this->starts($schedule, self::MONDAY));
        $this->book($schedule, ['starts_at' => $this->at('08:00')])->assertCreated();
        $this->assertSame(['08:30'], $this->starts($schedule, self::MONDAY));
    }

    /**
     * H6: una cantidad fija de consultas, sin una por horario ni por turno: una agenda abierta
     * de 00:00 a 23:55 cada 5 minutos, un día con un turno y una semana con muchos turnos y
     * bloqueos, la misma cantidad.
     */
    public function test_the_number_of_queries_does_not_grow_with_the_slots_or_the_appointments(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->scheduleWith($clinic, [], 1, ['slot_minutes' => 5]);
        $this->hoursFor($schedule, array_map(fn (int $weekday) => [$weekday, '00:00', '23:55'], range(1, 7)));
        $this->actAsReceptionWithAvailability($clinic);

        $this->appointmentIn($schedule, $this->monday('09:00'), AppointmentStatus::CONFIRMED, 10);
        $this->availability($schedule, 'from=' . self::MONDAY)->assertOk();
        $few = $this->countQueries(fn () => $this->availability($schedule, 'from=' . self::MONDAY)->assertOk());

        for ($index = 1; $index <= 40; $index++) {
            $this->appointmentIn($schedule, $this->monday('00:00')->addMinutes(240 * $index), AppointmentStatus::CONFIRMED, 10);
        }
        $this->blockFor($schedule, ['starts_at' => $this->monday('12:00'), 'ends_at' => $this->monday('13:00')]);
        $this->blockFor($schedule, ['starts_at' => $this->monday('12:00')->addDays(3), 'ends_at' => $this->monday('14:00')->addDays(3)]);

        $week = null;
        $many = $this->countQueries(function () use ($schedule, &$week) {
            $week = $this->availability($schedule, 'from=' . self::MONDAY . '&to=2026-11-22')->assertOk();
        });

        $this->assertSame($few, $many, 'La cantidad de consultas crece con los horarios o los turnos.');
        // 287 horarios por día (00:00 a 23:50), 7 días, menos los ocupados y los bloqueados.
        $this->assertGreaterThan(1800, array_sum(array_map('count', $week->json('data.days.*.slots'))));
    }

    /**
     * La garantía de la entrega (agenda con veterinario): todo horario que la disponibilidad
     * ofrece se da por la API (201) y todo candidato de la grilla (cada slot_minutes desde el
     * comienzo de cada tramo) que no ofrece se rechaza por el motivo esperado. Dos tramos que se
     * tocan y uno aparte, un bloqueo, un turno de la agenda, uno del mismo veterinario en otra
     * agenda, un sobreturno existente (ocupa), un cancelado y un ausente (no ocupan), un servicio
     * de 40 minutos y un paso de 15.
     */
    public function test_every_offered_slot_is_booked_and_every_other_candidate_is_rejected_for_its_reason(): void
    {
        [$schedule, $service] = $this->gridScenario();

        $this->assertGridMatchesBooking($schedule, "service_id={$service->id}", ['service_id' => $service->id], null, [
            'offered' => ['08:00', '09:15', '15:10', '15:25', '15:40'],
            'APPOINTMENT_IN_BLOCK' => ['08:15', '08:30', '08:45', '09:00'],
            'APPOINTMENT_OVERLAP' => ['09:30', '09:45', '10:00', '10:15', '14:10', '14:25', '14:40', '14:55'],
            'APPOINTMENT_OUTSIDE_HOURS' => ['10:30', '10:45', '15:55', '16:10', '16:25'],
        ]);
    }

    /**
     * La misma garantía con una duración propia (D2): 25 minutos, que mandan sobre el servicio de
     * 40 del pedido; el alta recibe el mismo servicio con ends_at.
     */
    public function test_with_its_own_duration_every_offered_slot_is_booked_with_ends_at(): void
    {
        [$schedule, $service] = $this->gridScenario();

        $this->assertGridMatchesBooking($schedule, "service_id={$service->id}&duration_minutes=25", ['service_id' => $service->id], 25, [
            'offered' => ['08:00', '08:15', '09:15', '09:30', '10:30', '14:10', '15:10', '15:25', '15:40', '15:55'],
            'APPOINTMENT_IN_BLOCK' => ['08:30', '08:45', '09:00'],
            'APPOINTMENT_OVERLAP' => ['09:45', '10:00', '10:15', '14:25', '14:40', '14:55'],
            'APPOINTMENT_OUTSIDE_HOURS' => ['10:45', '16:10', '16:25'],
        ]);
    }

    /**
     * La misma garantía en una agenda sin veterinario (solo el servicio): cuentan solo sus
     * turnos, no los de otra agenda sin veterinario a la misma hora.
     */
    public function test_in_a_schedule_without_veterinarian_every_offered_slot_is_booked(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic, ['duration_minutes' => 30]);
        $schedule = $this->scheduleIn($branch, ['service_id' => $service->id]);
        $this->hoursFor($schedule, [[1, '08:00', '10:10']]);
        $other = $this->scheduleIn($branch, ['service_id' => $service->id]);
        $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, 'schedules.availability']);

        $this->appointmentIn($other, $this->monday('08:00'), AppointmentStatus::CONFIRMED);
        $this->appointmentIn($schedule, $this->monday('09:00'), AppointmentStatus::CONFIRMED);
        $this->blockFor($schedule, ['starts_at' => $this->monday('09:30'), 'ends_at' => $this->monday('09:40')]);

        $this->assertGridMatchesBooking($schedule, '', [], null, [
            'offered' => ['08:00', '08:30'],
            'APPOINTMENT_IN_BLOCK' => ['09:30'],
            'APPOINTMENT_OVERLAP' => ['09:00'],
            'APPOINTMENT_OUTSIDE_HOURS' => ['10:00'],
        ]);
    }

    /**
     * La agenda de la grilla: el lunes de 08:00 a 10:00, de 10:00 a 11:00 (se tocan) y de 14:10 a
     * 16:30, cada 15 minutos; el mismo veterinario abierto de 08:00 a 20:00 en otra agenda.
     *
     * @return array{0: Schedule, 1: \App\Models\Service}
     */
    private function gridScenario(): array
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['duration_minutes' => 40]);
        $schedule = $this->scheduleWith($clinic, [['08:00', '10:00'], ['10:00', '11:00'], ['14:10', '16:30']], 1, ['slot_minutes' => 15]);
        $other = $this->scheduleIn($this->branchOf($schedule), ['veterinarian_id' => $schedule->veterinarian_id]);
        $this->hoursFor($other, [[1, '08:00', '20:00']]);
        $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, 'schedules.availability']);

        $this->blockFor($schedule, ['starts_at' => $this->monday('08:50'), 'ends_at' => $this->monday('09:10')]);
        $this->appointmentIn($schedule, $this->monday('10:05'), AppointmentStatus::CONFIRMED, 20);
        $this->appointmentIn($other, $this->monday('14:40'), AppointmentStatus::PENDING_CONFIRMATION, 10);
        $this->appointmentIn($schedule, $this->monday('08:00'), AppointmentStatus::CANCELLED, 60);
        $overbooked = $this->appointmentIn($schedule, $this->monday('15:00'), AppointmentStatus::PENDING_CONFIRMATION, 10);
        DB::table('appointments')->where('id', $overbooked)->update(['is_overbooked' => true]);
        $this->appointmentIn($schedule, $this->monday('15:30'), AppointmentStatus::NO_SHOW, 30);

        return [$schedule, $service];
    }

    /**
     * Recorre toda la grilla del lunes (cada slot_minutes desde el comienzo de cada tramo de la
     * agenda, leídos de la base) y da cada horario por la API, deshaciendo cada alta: lo ofrecido
     * responde 201 y cada rechazo, el error_code esperado (fuera del horario, bloqueo o
     * superposición). Con $duration, el alta lleva ends_at.
     *
     * @param array<string, list<string>> $expected 'offered' y cada error_code, con las horas "H:i"
     */
    private function assertGridMatchesBooking(Schedule $schedule, string $query, array $payload, ?int $duration, array $expected): void
    {
        $this->assertSame($expected['offered'], $this->starts($schedule, self::MONDAY, $query));

        $candidates = [];
        $ranges = DB::table('schedule_hours')->where('schedule_id', $schedule->id)->where('weekday', 1)->orderBy('starts_at')->get();
        foreach ($ranges as $range) {
            for ($time = $this->monday(substr($range->starts_at, 0, 5)); $time->lessThan($this->monday(substr($range->ends_at, 0, 5))); $time = $time->addMinutes($schedule->slot_minutes)) {
                $candidates[] = $time->format('H:i');
            }
        }
        $candidates = array_values(array_unique($candidates));
        $expectedTimes = array_merge(...array_values($expected));
        sort($expectedTimes);
        $this->assertSame($candidates, $expectedTimes, 'Lo esperado no cubre toda la grilla.');

        $actual = array_fill_keys(array_keys($expected), []);
        foreach ($candidates as $time) {
            DB::beginTransaction();

            try {
                $response = $this->book($schedule, [
                    'starts_at' => $this->at($time),
                    ...($duration === null ? [] : ['ends_at' => $this->monday($time)->addMinutes($duration)->format('Y-m-d\TH:i:s')]),
                    ...$payload,
                ]);
            } finally {
                DB::rollBack();
            }

            $actual[$response->getStatusCode() === 201 ? 'offered' : (string) $response->json('error_code')][] = $time;
        }

        $this->assertSame($expected, $actual);
    }

    private function countQueries(callable $request): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $request();

        return $count;
    }
}
