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

    private Schedule $unassigned;

    private Schedule $unassignedElsewhere;

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
        // Agendas solo de servicio (sin veterinario): una en la sucursal y otra en la otra (D1).
        $service = $this->serviceIn($this->clinic, ['name' => 'Vacunación']);
        $this->unassigned = $this->scheduleIn($this->branch, ['name' => 'Vacunatorio', 'service_id' => $service->id]);
        $this->unassignedElsewhere = $this->scheduleIn($this->otherBranch, ['name' => 'Vacunatorio 2', 'service_id' => $service->id]);
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
     * Los turnos de hoy de las agendas: [mío 09:00, colega 08:00, mío cancelado 10:00, colega
     * ausente 11:00, otra sucursal 09:00, mío de mañana 09:00, agenda sin veterinario de la
     * sucursal 10:30 y de la otra sucursal 10:30].
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
            'unassigned' => $this->appointmentIn($this->unassigned, $this->today('10:30')),
            'unassigned_elsewhere' => $this->appointmentIn($this->unassignedElsewhere, $this->today('10:30')),
        ];
    }

    public function test_the_reception_sees_the_whole_current_branch_without_cancelled(): void
    {
        $day = $this->day();
        $this->actAsRole('recepcionista');

        $this->assertSame([$day['colleague'], $day['mine'], $day['unassigned'], $day['absent']], $this->ids());
        $this->queue()
            ->assertJsonPath('meta.date', self::TODAY)
            ->assertJsonPath('meta.branch_id', $this->branch->id)
            ->assertJsonPath('meta.veterinarian_id', null)
            ->assertJsonPath('meta.includes_unassigned', false)
            ->assertJsonPath('meta.truncated', false);

        $this->assertSame([$day['mine']], $this->ids("veterinarian_id={$this->mine->veterinarian_id}"));
        $this->assertSame([$day['colleague'], $day['absent']], $this->ids("schedule_id={$this->colleague->id}"));
        $this->assertSame([$day['tomorrow']], $this->ids('date=2026-11-11'));
    }

    /**
     * D1: por defecto, los de su ficha y los de las agendas sin veterinario de su sucursal actual
     * (no los de otra sucursal); con veterinarian_id, solo los de ese veterinario, también el suyo.
     */
    public function test_the_veterinarian_sees_their_own_and_the_unassigned_by_default_and_the_branch_with_all(): void
    {
        $day = $this->day();
        Sanctum::actingAs($this->vetUser);

        $this->assertSame([$day['mine'], $day['unassigned']], $this->ids());
        $this->queue()
            ->assertJsonPath('meta.veterinarian_id', $this->mine->veterinarian_id)
            ->assertJsonPath('meta.includes_unassigned', true);

        // Es una vista por defecto, no una restricción (pregunta 25).
        $this->assertSame([$day['colleague'], $day['mine'], $day['unassigned'], $day['absent']], $this->ids('all=1'));
        $this->queue('all=1')
            ->assertJsonPath('meta.veterinarian_id', null)
            ->assertJsonPath('meta.includes_unassigned', false);
        $this->assertSame([$day['colleague'], $day['absent']], $this->ids("veterinarian_id={$this->colleague->veterinarian_id}"));

        // Con su propio veterinarian_id, solo los suyos: sin las agendas sin veterinario.
        $this->assertSame([$day['mine']], $this->ids("veterinarian_id={$this->mine->veterinarian_id}"));
        $this->queue("veterinarian_id={$this->mine->veterinarian_id}")->assertJsonPath('meta.includes_unassigned', false);
    }

    public function test_a_veterinarian_without_a_card_or_with_a_deleted_one_sees_the_branch(): void
    {
        $day = $this->day();
        $this->actAsRole('veterinario');
        $this->assertSame([$day['colleague'], $day['mine'], $day['unassigned'], $day['absent']], $this->ids());
        $this->queue()->assertJsonPath('meta.includes_unassigned', false);

        CurrentClinic::run($this->clinic, fn () => Veterinarian::query()->where('user_id', $this->vetUser->id)->firstOrFail()->delete());
        Sanctum::actingAs($this->vetUser->fresh());
        $this->assertSame([$day['colleague'], $day['mine'], $day['unassigned'], $day['absent']], $this->ids());
    }

    public function test_the_current_branch_decides_the_queue(): void
    {
        $day = $this->day();
        $this->actAsRole('recepcionista', ['current_branch_id' => $this->otherBranch->id]);

        $this->assertSame([$day['elsewhere'], $day['unassigned_elsewhere']], $this->ids());
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
     * paciente (con especie y raza: sin raza, la relación no consulta y no se probaría), su
     * cliente y una de dos agendas con su veterinario y su servicio.
     */
    public function test_the_number_of_queries_does_not_grow_with_the_appointments(): void
    {
        $service = $this->serviceIn($this->clinic);
        DB::table('schedules')->whereIn('id', [$this->mine->id, $this->colleague->id])->update(['service_id' => $service->id]);
        $this->actAsRole('recepcionista');
        $this->appointmentIn($this->mine, $this->today('08:00'));
        $this->giveEveryPatientABreed();
        $this->queue()->assertOk()->assertJsonPath('data.0.patient.breed.name', 'Labrador Retriever');

        $one = $this->countQueries(fn () => $this->queue()->assertOk()->assertJsonCount(1, 'data'));

        for ($index = 1; $index <= 11; $index++) {
            $this->appointmentIn($index % 2 === 0 ? $this->mine : $this->colleague, $this->today('08:00')->addMinutes(30 * $index));
        }

        $this->giveEveryPatientABreed();

        $many = $this->countQueries(fn () => $this->queue()->assertOk()->assertJsonCount(12, 'data'));

        $this->assertSame($one, $many, 'La cantidad de consultas crece con los turnos (N+1).');
    }

    /**
     * D3: hasta LIMIT (200) sin paginar; con más, corta en 200 (los primeros por hora e id) y
     * avisa con meta.truncated.
     */
    public function test_at_most_the_limit_and_it_warns_when_it_cuts(): void
    {
        $this->assertSame(200, AppointmentQueueService::LIMIT);
        $this->actAsRole('recepcionista');
        $ids = $this->manyAppointments(201);

        $response = $this->queue()->assertOk()
            ->assertJsonCount(200, 'data')
            ->assertJsonPath('meta.truncated', true)
            ->assertJsonMissingPath('meta.pagination');
        $this->assertSame(array_slice($ids, 0, 200), $response->json('data.*.id'));

        // Con 200 justos, no corta.
        DB::table('appointments')->where('id', end($ids))->delete();
        $this->queue()->assertOk()->assertJsonCount(200, 'data')->assertJsonPath('meta.truncated', false);
    }

    /**
     * Dos turnos a la misma hora (un sobreturno): por starts_at y después por id, aunque el de
     * id mayor se haya cargado primero.
     */
    public function test_appointments_at_the_same_time_are_ordered_by_id(): void
    {
        $this->actAsRole('recepcionista');
        $first = $this->appointmentIn($this->mine, $this->today('09:00'));
        $second = $this->appointmentIn($this->mine, $this->today('09:00'), AppointmentStatus::PENDING_CONFIRMATION);
        $early = $this->appointmentIn($this->colleague, $this->today('08:00'));
        DB::table('appointments')->where('id', $second)->update(['is_overbooked' => true]);

        // El cargado primero pasa a tener el id mayor.
        $renumbered = $first + 1000;
        DB::table('appointments')->where('id', $first)->update(['id' => $renumbered]);

        $this->assertSame([$early, $second, $renumbered], $this->ids());

        // Con el índice (branch_id, starts_at), SQLite y MySQL devuelven los empates por id aunque
        // la consulta no lo pida: el orden tiene que estar en la consulta, no depender del plan.
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->queue()->assertOk();
        $queue = array_values(array_filter($queries, fn (string $sql) => preg_match('/^select \* from [`"]appointments[`"]/', $sql) === 1));
        $this->assertNotEmpty($queue, implode("\n", $queries));
        $this->assertMatchesRegularExpression('/order by [`"]starts_at[`"] asc, [`"]id[`"] asc/', $queue[0]);
    }

    /**
     * $count turnos de hoy en la agenda propia, cada 5 minutos desde las 00:00, con un solo
     * paciente; devuelve los ids en el orden de la cola.
     *
     * @return list<int>
     */
    private function manyAppointments(int $count): array
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $creator = User::factory()->for($this->clinic)->create()->id;
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $startsAt = $this->today('00:00')->addMinutes(5 * $index);
            $rows[] = [
                'clinic_id' => $this->clinic->id,
                'branch_id' => $this->branch->id,
                'schedule_id' => $this->mine->id,
                'veterinarian_id' => $this->mine->veterinarian_id,
                'client_id' => $patient->client_id,
                'patient_id' => $patient->id,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->addMinutes(5)->format('Y-m-d H:i:s'),
                'status' => AppointmentStatus::CONFIRMED->value,
                'created_by' => $creator,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('appointments')->insert($rows);

        return DB::table('appointments')->where('schedule_id', $this->mine->id)->orderBy('starts_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function giveEveryPatientABreed(): void
    {
        DB::table('patients')->where('clinic_id', $this->clinic->id)->update([
            'species_id' => $this->globalSpeciesId('Canino'),
            'breed_id' => $this->globalBreedId('Labrador Retriever'),
        ]);
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
