<?php

namespace Tests\Feature\Appointments;

use App\Http\Requests\IndexAppointmentRequest;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Entrega front-03 (pregunta 1 de la F3 del frontend, R-085): GET appointments acepta per_page
 * de 1 a 500, para que la semana de una sucursal entre en un pedido; los demás listados siguen
 * hasta 100 (ListFilters). El valor por defecto (10) y la paginación (con total) no cambian, y
 * 500 turnos se listan con la misma cantidad de consultas que uno (sin N+1).
 */
class AppointmentPerPageTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAppointments;
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function week(string $query = ''): string
    {
        $to = CarbonImmutable::parse(self::MONDAY)->addDays(6)->toDateString();

        return '/api/appointments?from=' . self::MONDAY . "&to={$to}" . ($query === '' ? '' : "&{$query}");
    }

    public function test_per_page_goes_up_to_500_and_the_default_is_still_10(): void
    {
        $clinic = $this->createClinic();
        $this->actAsReception($clinic);

        $this->getJson($this->week())->assertOk()->assertJsonPath('meta.pagination.per_page', 10);
        $this->getJson($this->week('per_page=100'))->assertOk()->assertJsonPath('meta.pagination.per_page', 100);
        $this->getJson($this->week('per_page=500&page=1'))
            ->assertOk()
            ->assertJsonPath('meta.pagination', ['total' => 0, 'per_page' => 500, 'current_page' => 1, 'last_page' => 1]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function outOfRange(): array
    {
        return [
            '501' => ['501'],
            'cero' => ['0'],
            'negativo' => ['-1'],
            'texto' => ['abc'],
            'decimal' => ['10.5'],
        ];
    }

    #[DataProvider('outOfRange')]
    public function test_per_page_out_of_range_is_rejected_with_its_message(string $perPage): void
    {
        $clinic = $this->createClinic();
        $this->actAsReception($clinic);

        $this->getJson($this->week("per_page={$perPage}"))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.per_page', [IndexAppointmentRequest::PER_PAGE_OUT_OF_RANGE]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function otherLists(): array
    {
        return [
            'clientes' => ['/api/clients'],
            'pacientes' => ['/api/patients'],
            'sucursales' => ['/api/branches'],
            'depósitos' => ['/api/branches/{branch}/warehouses'],
            'usuarios' => ['/api/users'],
            'veterinarios' => ['/api/veterinarians'],
            'servicios' => ['/api/services'],
            'agendas' => ['/api/schedules'],
            'bloqueos' => ['/api/schedules/{schedule}/blocks'],
            'auditoría' => ['/api/audit-logs'],
        ];
    }

    #[DataProvider('otherLists')]
    public function test_the_other_lists_still_stop_at_100(string $url): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($branch);
        Sanctum::actingAs($this->userWithRole($clinic, 'administrador'));
        $url = str_replace(['{branch}', '{schedule}'], [$branch->id, $schedule->id], $url);

        $this->getJson("{$url}?per_page=100")->assertOk()->assertJsonPath('meta.pagination.per_page', 100);
        $this->getJson("{$url}?per_page=101")->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
        $this->getJson("{$url}?per_page=500")->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Con 1 turno y con 300 (de tres agendas, con paciente, solo con cliente o con un contacto,
     * con servicio o sin él), las mismas consultas: las relaciones del Resource van en el with.
     * El primer pedido llena la caché de permisos de Spatie y no se cuenta (como front-02).
     */
    public function test_listing_300_appointments_takes_the_same_queries_as_one(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        $schedules = [
            $this->openSchedule($branch, ['service_id' => $service->id]),
            $this->openSchedule($branch),
            $this->openSchedule($branch, ['veterinarian_id' => null, 'service_id' => $service->id]),
        ];
        $creator = User::factory()->for($clinic)->create();
        $this->actAsReception($clinic);

        $this->insertAppointments($clinic, $schedules, $creator, 0, 1);
        $this->getJson($this->week('per_page=500'))->assertOk();

        $one = $this->countQueries(fn () => $this->getJson($this->week('per_page=500'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient.name', 'Paciente 0')
            ->assertJsonPath('data.0.service.id', $service->id));

        $this->insertAppointments($clinic, $schedules, $creator, 1, 300);

        $many = $this->countQueries(fn () => $this->getJson($this->week('per_page=500'))
            ->assertOk()
            ->assertJsonCount(300, 'data')
            ->assertJsonPath('meta.pagination', ['total' => 300, 'per_page' => 500, 'current_page' => 1, 'last_page' => 1]));

        $this->assertSame($one, $many, 'La cantidad de consultas crece con los turnos (N+1).');
    }

    /**
     * El total avisa al frontend que faltan turnos: con más de los pedidos, total los cuenta a todos.
     */
    public function test_the_total_counts_the_appointments_left_out(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $creator = User::factory()->for($clinic)->create();
        $this->actAsReception($clinic);
        $this->insertAppointments($clinic, [$schedule], $creator, 0, 12);

        $this->getJson($this->week('per_page=5'))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagination', ['total' => 12, 'per_page' => 5, 'current_page' => 1, 'last_page' => 3]);
    }

    /**
     * Turnos $from a $to - 1, sin la API (en un insert por tanda): uno de cada tres con paciente
     * (y su cliente), otro solo con cliente y otro con un contacto; repartidos entre las agendas
     * y los seis días desde el lunes, de a 30 minutos desde las 08:00 de Asunción.
     *
     * @param list<Schedule> $schedules
     */
    private function insertAppointments(Clinic $clinic, array $schedules, User $creator, int $from, int $to): void
    {
        $rows = [];

        for ($index = $from; $index < $to; $index++) {
            $schedule = $schedules[$index % count($schedules)];
            $slot = intdiv($index, count($schedules));
            $startsAt = CarbonImmutable::parse(self::MONDAY . ' 08:00', 'America/Asuncion')
                ->addDays($slot % 6)
                ->addMinutes(30 * intdiv($slot, 6));
            $who = match ($index % 3) {
                0 => (function () use ($clinic, $index) {
                    $patient = $this->patientOf($this->clientIn($clinic), ['name' => "Paciente {$index}"]);

                    return ['client_id' => $patient->client_id, 'patient_id' => $patient->id];
                })(),
                1 => ['client_id' => $this->clientIn($clinic)->id, 'patient_id' => null],
                default => ['contact_name' => "Contacto {$index}", 'contact_phone' => '981123456'],
            };

            $rows[] = [
                'clinic_id' => $clinic->id,
                'branch_id' => $schedule->branch_id,
                'schedule_id' => $schedule->id,
                'veterinarian_id' => $schedule->veterinarian_id,
                'service_id' => $schedule->service_id,
                'client_id' => null,
                'patient_id' => null,
                'contact_name' => null,
                'contact_phone' => null,
                ...$who,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->addMinutes(30)->format('Y-m-d H:i:s'),
                'status' => 'confirmed',
                'created_by' => $creator->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('appointments')->insert($chunk);
        }
    }
}
