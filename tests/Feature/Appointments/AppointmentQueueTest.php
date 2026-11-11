<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Veterinarian;
use App\Services\AppointmentQueueService;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Cola del día (FASE 3, entrega 3.4; P8 y pregunta 25): los turnos del día en la sucursal
 * actual del usuario, sin los cancelados, por hora. Quien tiene ficha de veterinario ve por
 * defecto los suyos y con all=1 los de la sucursal; la recepción, toda la sucursal. Sin
 * sucursal actual, 422 APPOINTMENT_QUEUE_NO_BRANCH. Sin N+1.
 *
 * El reloj, el martes 10/11/2026 a las 07:00 de Asunción (TODAY).
 */
class AppointmentQueueTest extends TestCase
{
    use InteractsWithAppointments;

    private Clinic $clinic;

    private Branch $branch;

    private Branch $otherBranch;

    private User $vetUser;

    private Schedule $mine;

    private Schedule $colleague;

    private Schedule $elsewhere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();

        $this->clinic = $this->createClinic();
        $this->branch = $this->defaultBranchIn($this->clinic);
        $this->otherBranch = $this->branchIn($this->clinic);

        $this->vetUser = $this->veterinarianUser($this->clinic, [$this->branch], ['current_branch_id' => $this->branch->id]);
        $this->mine = $this->scheduleIn($this->branch, ['name' => 'Consultorio 1', 'veterinarian_id' => $this->veterinarianOf($this->vetUser)->id]);
        $this->colleague = $this->scheduleIn($this->branch, ['name' => 'Consultorio 2', 'veterinarian_id' => $this->veterinarianIn($this->branch)->id]);
        $this->elsewhere = $this->scheduleIn($this->otherBranch, ['veterinarian_id' => $this->veterinarianIn($this->otherBranch)->id]);
    }

    private function queue(string $query = ''): TestResponse
    {
        return $this->getJson('/api/appointments/queue' . ($query === '' ? '' : "?{$query}"));
    }

    /**
     * @return list<int>
     */
    private function ids(string $query = ''): array
    {
        return $this->queue($query)->assertOk()->json('data.*.id');
    }

    private function actAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRoles($this->clinic, [$role], [$this->branch], ['current_branch_id' => $this->branch->id, ...$attributes]);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Los turnos de hoy de las tres agendas: [mío 09:00, colega 08:00, mío cancelado 10:00,
     * colega ausente 11:00, otra sucursal 09:00, mío de mañana 09:00].
     *
     * @return array<string, int>
     */
    private function day(): array
    {
        return [
            'mine' => $this->appointmentIn($this->mine, $this->today('09:00')),
            'colleague' => $this->appointmentIn($this->colleague, $this->today('08:00'), AppointmentStatus::PENDING_CONFIRMATION),
            'cancelled' => $this->appointmentIn($this->mine, $this->today('10:00'), AppointmentStatus::CANCELLED),
            'absent' => $this->appointmentIn($this->colleague, $this->today('11:00'), AppointmentStatus::NO_SHOW),
            'elsewhere' => $this->appointmentIn($this->elsewhere, $this->today('09:00')),
            'tomorrow' => $this->appointmentIn($this->mine, $this->today('09:00')->addDay()),
        ];
    }

    public function test_the_reception_sees_the_whole_current_branch_without_cancelled(): void
    {
        $day = $this->day();
        $this->actAsRole('recepcionista');

        $this->assertSame([$day['colleague'], $day['mine'], $day['absent']], $this->ids());
        $this->queue()
            ->assertJsonPath('meta.date', self::TODAY)
            ->assertJsonPath('meta.branch_id', $this->branch->id)
            ->assertJsonPath('meta.veterinarian_id', null);

        $this->assertSame([$day['mine']], $this->ids("veterinarian_id={$this->mine->veterinarian_id}"));
        $this->assertSame([$day['colleague'], $day['absent']], $this->ids("schedule_id={$this->colleague->id}"));
        $this->assertSame([$day['tomorrow']], $this->ids('date=2026-11-11'));
    }

    public function test_the_veterinarian_sees_their_own_by_default_and_the_branch_with_all(): void
    {
        $day = $this->day();
        Sanctum::actingAs($this->vetUser);

        $this->assertSame([$day['mine']], $this->ids());
        $this->queue()->assertJsonPath('meta.veterinarian_id', $this->mine->veterinarian_id);

        // Es una vista por defecto, no una restricción (pregunta 25).
        $this->assertSame([$day['colleague'], $day['mine'], $day['absent']], $this->ids('all=1'));
        $this->queue('all=1')->assertJsonPath('meta.veterinarian_id', null);
        $this->assertSame([$day['colleague'], $day['absent']], $this->ids("veterinarian_id={$this->colleague->veterinarian_id}"));
    }

    public function test_a_veterinarian_without_a_card_or_with_a_deleted_one_sees_the_branch(): void
    {
        $day = $this->day();
        $this->actAsRole('veterinario');
        $this->assertSame([$day['colleague'], $day['mine'], $day['absent']], $this->ids());

        CurrentClinic::run($this->clinic, fn () => Veterinarian::query()->where('user_id', $this->vetUser->id)->firstOrFail()->delete());
        Sanctum::actingAs($this->vetUser->fresh());
        $this->assertSame([$day['colleague'], $day['mine'], $day['absent']], $this->ids());
    }

    public function test_the_current_branch_decides_the_queue(): void
    {
        $day = $this->day();
        $this->actAsRole('recepcionista', ['current_branch_id' => $this->otherBranch->id]);

        $this->assertSame([$day['elsewhere']], $this->ids());
    }

    public function test_without_a_current_branch_422(): void
    {
        $this->actAsRole('recepcionista', ['current_branch_id' => null]);

        $this->assertErrorCode($this->queue(), 422, 'APPOINTMENT_QUEUE_NO_BRANCH');
        $this->queue()->assertJsonPath('message', 'Elegí tu sucursal actual para ver la cola del día.');
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actAsRole('recepcionista');

        $this->queue('date=2026-11-10T10:00:00Z&all=quizás&veterinarian_id=999999&schedule_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'all', 'veterinarian_id', 'schedule_id']);
    }

    public function test_the_shape_of_each_appointment(): void
    {
        $client = $this->clientIn($this->clinic, ['first_name' => 'Rocío', 'last_name' => 'Benítez']);
        $patient = $this->patientOf($client, ['name' => 'Luna', 'breed_id' => $this->globalBreedId('Labrador Retriever')]);
        $this->hoursFor($this->mine, [[2, '08:00', '20:00']]);
        $user = $this->userIn($this->clinic, [...self::APPOINTMENT_PERMISSIONS, 'appointments.queue']);
        DB::table('users')->where('id', $user->id)->update(['current_branch_id' => $this->branch->id]);
        Sanctum::actingAs($user->fresh());

        $withPatient = $this->booked($this->mine, [
            'starts_at' => $this->at('09:00', self::TODAY),
            'patient_id' => $patient->id,
            'contact_name' => null,
            'contact_phone' => null,
        ]);
        $contact = $this->booked($this->mine, ['starts_at' => $this->at('10:00', self::TODAY)]);
        $this->setStatus($withPatient, AppointmentStatus::CHECKED_IN, ['checked_in_at' => $this->today('08:50')->format('Y-m-d H:i:s'), 'is_overbooked' => true]);

        $this->queue()->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $withPatient)
            ->assertJsonPath('data.0.patient.name', 'Luna')
            ->assertJsonPath('data.0.patient.species.name', 'Canino')
            ->assertJsonPath('data.0.patient.breed.name', 'Labrador Retriever')
            ->assertJsonPath('data.0.client.display_name', 'Rocío Benítez')
            ->assertJsonPath('data.0.status.value', 'checked_in')
            ->assertJsonPath('data.0.is_overbooked', true)
            ->assertJsonPath('data.0.checked_in_at', '2026-11-10T11:50:00.000000Z')
            ->assertJsonPath('data.0.schedule.name', 'Consultorio 1')
            ->assertJsonPath('data.0.starts_at', '2026-11-10T12:00:00.000000Z')
            ->assertJsonPath('data.1.id', $contact)
            ->assertJsonPath('data.1.patient', null)
            ->assertJsonPath('data.1.contact_name', 'Juan, el de la perra Luna')
            ->assertJsonPath('data.1.checked_in_at', null);
    }

    public function test_near_midnight_today_is_the_day_of_asuncion(): void
    {
        $late = $this->appointmentIn($this->mine, $this->today('23:00'), AppointmentStatus::CONFIRMED, 30);
        $this->actAsRole('recepcionista');

        // 23:40 del martes en Asunción: en UTC ya es miércoles.
        $this->freezeToday('23:40:00');

        $this->assertSame([$late], $this->ids());
        $this->queue()->assertJsonPath('meta.date', self::TODAY);
    }

    public function test_nothing_of_another_clinic(): void
    {
        $foreign = $this->createClinic();
        $foreignBranch = $this->defaultBranchIn($foreign);
        $foreignSchedule = $this->scheduleIn($foreignBranch, ['veterinarian_id' => $this->veterinarianIn($foreignBranch)->id]);
        $this->appointmentIn($foreignSchedule, $this->today('09:00'));

        $this->actAsRole('recepcionista');

        $this->assertSame([], $this->ids());
        // Una agenda o un veterinario de otra clínica: 422, como el listado.
        $this->queue("schedule_id={$foreignSchedule->id}&veterinarian_id={$foreignSchedule->veterinarian_id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['schedule_id', 'veterinarian_id']);
    }

    public function test_without_the_permission_403(): void
    {
        $user = $this->userIn($this->clinic, ['appointments.index']);
        DB::table('users')->where('id', $user->id)->update(['current_branch_id' => $this->branch->id]);
        Sanctum::actingAs($user->fresh());

        $this->assertErrorCode($this->queue(), 403, 'FORBIDDEN');
    }

    /**
     * Sin N+1: la misma cantidad de consultas con un turno que con doce, cada uno con su
     * paciente, su cliente y una de dos agendas con su veterinario y su servicio.
     */
    public function test_the_number_of_queries_does_not_grow_with_the_appointments(): void
    {
        $service = $this->serviceIn($this->clinic);
        DB::table('schedules')->whereIn('id', [$this->mine->id, $this->colleague->id])->update(['service_id' => $service->id]);
        $this->actAsRole('recepcionista');
        $this->appointmentIn($this->mine, $this->today('08:00'));
        $this->queue()->assertOk();

        $one = $this->countQueries(fn () => $this->queue()->assertOk()->assertJsonCount(1, 'data'));

        for ($index = 1; $index <= 11; $index++) {
            $this->appointmentIn($index % 2 === 0 ? $this->mine : $this->colleague, $this->today('08:00')->addMinutes(30 * $index));
        }

        $many = $this->countQueries(fn () => $this->queue()->assertOk()->assertJsonCount(12, 'data'));

        $this->assertSame($one, $many, 'La cantidad de consultas crece con los turnos (N+1).');
    }

    public function test_at_most_the_limit_without_pagination(): void
    {
        $this->actAsRole('recepcionista');
        $this->appointmentIn($this->mine, $this->today('08:00'));
        $this->appointmentIn($this->mine, $this->today('09:00'));

        $this->assertSame(200, AppointmentQueueService::LIMIT);
        $this->queue()->assertOk()->assertJsonMissingPath('meta.pagination');
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
