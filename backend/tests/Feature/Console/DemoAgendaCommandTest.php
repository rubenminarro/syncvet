<?php

namespace Tests\Feature\Console;

use App\Console\Commands\DemoAgendaCommand;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Services\AppointmentOwnership;
use App\Services\AppointmentService;
use App\Services\AppointmentTransitionService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * php artisan demo:agenda (demo-02): agendas y turnos ficticios de la semana en curso en la
 * Clínica A del DemoSeeder, por los Services, idempotente y sin tocar otros datos.
 */
class DemoAgendaCommandTest extends TestCase
{
    /**
     * Miércoles 18/11/2026 a las 10:15 en Asunción (UTC-3).
     */
    private const WEDNESDAY = '2026-11-18 10:15:00';

    private const TZ = 'America/Asuncion';

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'Demo#' . Str::random(8) . random_int(10, 99)]);
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
    }

    public function test_it_creates_the_agendas_and_a_week_of_appointments_in_every_status(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY, self::TZ));

        $this->artisan('demo:agenda')->assertSuccessful();

        $this->inDemoClinic(function () {
            $schedules = Schedule::with('hours')->orderBy('id')->get();
            $this->assertSame(
                ['Demo Consultorio 1', 'Demo Consultorio 2', 'Demo Peluquería', 'Demo Guardia', 'Demo Vacunación'],
                $schedules->pluck('name')->all()
            );
            $this->assertTrue($schedules->every(fn (Schedule $s) => $s->is_active && $s->hours->isNotEmpty()));
            $this->assertSame([Branch::where('code', 'SUC-01')->value('id')], $schedules->pluck('branch_id')->unique()->values()->all());

            $vetA = Veterinarian::where('user_id', User::where('email', 'vet-a@example.com')->value('id'))->sole();
            $this->assertSame([$vetA->id], $schedules->pluck('veterinarian_id')->filter()->values()->all());
            $this->assertSame(4, $schedules->whereNull('veterinarian_id')->count());

            $appointments = Appointment::all();
            $this->assertGreaterThan(50, $appointments->count());

            // La semana de Asunción: lunes 16 a domingo 22.
            foreach ($appointments as $appointment) {
                $local = $appointment->starts_at->setTimezone(self::TZ)->format('Y-m-d');
                $this->assertTrue($local >= '2026-11-16' && $local <= '2026-11-22', $local);
            }

            foreach (AppointmentStatus::cases() as $status) {
                $this->assertTrue($appointments->contains('status', $status), "Falta {$status->value}.");
            }

            $now = CarbonImmutable::parse(self::WEDNESDAY, self::TZ);

            foreach ($appointments as $appointment) {
                if (in_array($appointment->status, [AppointmentStatus::ATTENDED, AppointmentStatus::NO_SHOW], true)) {
                    $this->assertTrue($appointment->starts_at->lessThanOrEqualTo($now), 'Atendido o ausente en una hora que ya pasó.');
                }

                if ($appointment->status === AppointmentStatus::CHECKED_IN) {
                    $this->assertSame('2026-11-18', $appointment->starts_at->setTimezone(self::TZ)->format('Y-m-d'));
                }

                $this->assertSame(DemoAgendaCommand::NOTES, $appointment->notes);
            }

            $today = $appointments->filter(fn ($a) => $a->starts_at->setTimezone(self::TZ)->format('Y-m-d') === '2026-11-18');
            $this->assertGreaterThan(5, $today->count());
            $this->assertGreaterThan(1, $today->pluck('schedule_id')->unique()->count(), 'Hoy, en varias agendas.');

            // Un sobreturno, en la agenda de vet-a, y un turno solo con contacto.
            $overbooked = $appointments->where('is_overbooked', true);
            $this->assertCount(1, $overbooked);
            $this->assertSame($vetA->id, $overbooked->first()->veterinarian_id);
            $contact = $appointments->whereNull('client_id')->sole();
            $this->assertNull($contact->patient_id);
            $this->assertSame(DemoAgendaCommand::CONTACTS[0]['name'], $contact->contact_name);

            // Un bloqueo con un turno que ocupa el horario adentro, hoy y por venir.
            $block = ScheduleBlock::sole();
            $inside = Appointment::where('schedule_id', $block->schedule_id)
                ->whereIn('status', AppointmentStatus::occupyingValues())
                ->where('starts_at', '<', $block->ends_at->format('Y-m-d H:i:s'))
                ->where('ends_at', '>', $block->starts_at->format('Y-m-d H:i:s'))
                ->count();
            $this->assertSame(1, $inside);
            $this->assertSame('2026-11-18', $block->starts_at->setTimezone(self::TZ)->format('Y-m-d'));
            $this->assertTrue($block->starts_at->greaterThan($now));

            // Los servicios "Demo …", activos.
            $this->assertSame(4, Service::where('name', 'like', 'Demo %')->where('status', 'active')->count());
        });
    }

    public function test_it_is_idempotent_and_keeps_the_agenda_ids(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY, self::TZ));

        $this->artisan('demo:agenda')->assertSuccessful();
        $first = $this->snapshot();

        $this->artisan('demo:agenda')->assertSuccessful();
        $second = $this->snapshot();

        $this->assertSame($first['schedules'], $second['schedules'], 'Las agendas conservan su id.');
        $this->assertSame($first['services'], $second['services'], 'Los servicios se reutilizan.');
        $this->assertSame($first['statuses'], $second['statuses']);
        $this->assertSame($first['blocks'], $second['blocks']);
        $this->assertSame($first['appointments'], $second['appointments']);
    }

    public function test_a_second_run_on_another_day_regenerates_the_current_week(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY, self::TZ));
        $this->artisan('demo:agenda')->assertSuccessful();

        // El lunes siguiente: la semana del 23 al 29; nada de la anterior, salvo lo que exige pasado.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 15:00:00', self::TZ));
        $this->artisan('demo:agenda')->assertSuccessful();

        $this->inDemoClinic(function () {
            $this->assertSame(0, Appointment::where('starts_at', '<', CarbonImmutable::parse('2026-11-22 00:00:00', self::TZ)->utc()->format('Y-m-d H:i:s'))->count());
            $this->assertSame(1, ScheduleBlock::count());
        });
    }

    public function test_it_does_not_touch_other_data_nor_the_three_demo_users(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY, self::TZ));

        $clinicA = Clinic::where('ruc', DemoSeeder::CLINICS[0]['ruc'])->sole();
        $clinicB = Clinic::where('ruc', DemoSeeder::CLINICS[1]['ruc'])->sole();

        // Una agenda propia de la clínica, con un turno: no es de la demo.
        [$other, $otherAppointment] = CurrentClinic::run($clinicA, function () {
            $service = Service::create(['code' => 'SRV-900001', 'name' => 'Consulta propia', 'category' => 'consultation', 'duration_minutes' => 30, 'standard_price' => '1000.00', 'tax_rate' => 10, 'status' => 'active']);
            $schedule = Schedule::create(['branch_id' => DB::table('branches')->where('clinic_id', CurrentClinic::id())->value('id'), 'name' => 'Agenda propia', 'service_id' => $service->id, 'slot_minutes' => 30, 'is_active' => true]);
            $appointment = Appointment::create([
                'schedule_id' => $schedule->id, 'branch_id' => $schedule->branch_id, 'service_id' => $service->id,
                'contact_name' => 'Persona Inventada', 'contact_phone' => '+595981000999',
                'starts_at' => CarbonImmutable::parse('2026-11-18 10:00:00', self::TZ), 'ends_at' => CarbonImmutable::parse('2026-11-18 10:30:00', self::TZ),
                'status' => 'pending_confirmation', 'created_by' => User::where('email', 'admin-a@example.com')->value('id'),
            ]);

            return [$schedule, $appointment];
        });

        $users = fn () => DB::table('users')->whereIn('email', ['admin-a@example.com', 'recepcion-a@example.com', 'vet-a@example.com'])->orderBy('id')->get()->map(fn ($u) => (array) $u)->all();
        $branchUser = fn () => DB::table('branch_user')->orderBy('user_id')->orderBy('branch_id')->get()->map(fn ($r) => (array) $r)->all();
        $roles = fn () => DB::table('model_has_roles')->orderBy('model_id')->get()->map(fn ($r) => (array) $r)->all();
        $vets = fn () => DB::table('veterinarians')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $clinicBData = fn () => [
            DB::table('schedules')->where('clinic_id', $clinicB->id)->count(),
            DB::table('appointments')->where('clinic_id', $clinicB->id)->count(),
            DB::table('services')->where('clinic_id', $clinicB->id)->count(),
        ];
        $before = [$users(), $branchUser(), $roles(), $vets(), $clinicBData(), DB::table('patients')->get()->map(fn ($r) => (array) $r)->all(), DB::table('clients')->get()->map(fn ($r) => (array) $r)->all()];
        $otherRow = (array) DB::table('appointments')->where('id', $otherAppointment->id)->first();

        $this->artisan('demo:agenda')->assertSuccessful();
        $this->artisan('demo:agenda')->assertSuccessful();

        $after = [$users(), $branchUser(), $roles(), $vets(), $clinicBData(), DB::table('patients')->get()->map(fn ($r) => (array) $r)->all(), DB::table('clients')->get()->map(fn ($r) => (array) $r)->all()];
        $this->assertSame($before, $after);
        $this->assertSame($otherRow, (array) DB::table('appointments')->where('id', $otherAppointment->id)->first());
        $this->assertSame('Agenda propia', DB::table('schedules')->where('id', $other->id)->value('name'));
    }

    /**
     * Desvío 5 de demo-02: solo la sucursal del DemoSeeder (SUC-01), aunque haya otra activa y sea
     * la por defecto (como la que deja Postman). Una "Demo …" de una corrida anterior en esa otra
     * sucursal se elimina con sus turnos; lo que no es "Demo …" en ella queda.
     */
    public function test_it_only_uses_the_demo_branch_and_removes_its_old_agendas_elsewhere(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY, self::TZ));
        $clinicA = Clinic::where('ruc', DemoSeeder::CLINICS[0]['ruc'])->sole();
        $admin = User::where('email', 'admin-a@example.com')->value('id');

        [$postman, $old, $ownAppointment] = CurrentClinic::run($clinicA, function () use ($admin) {
            Branch::where('code', 'SUC-01')->update(['is_default' => false]);
            $postman = Branch::create(['code' => 'SUC-PM', 'name' => 'Sucursal de usuarios (Postman)', 'establishment' => '009', 'is_default' => true, 'is_active' => true]);
            $service = Service::create(['code' => 'SRV-900002', 'name' => 'Consulta propia', 'category' => 'consultation', 'duration_minutes' => 30, 'standard_price' => '1000.00', 'tax_rate' => 10, 'status' => 'active']);
            $make = function (string $name, string $contact) use ($postman, $service, $admin) {
                $schedule = Schedule::create(['branch_id' => $postman->id, 'name' => $name, 'service_id' => $service->id, 'slot_minutes' => 30, 'is_active' => true]);
                $appointment = Appointment::create([
                    'schedule_id' => $schedule->id, 'branch_id' => $postman->id, 'service_id' => $service->id,
                    'contact_name' => $contact, 'contact_phone' => '+595981000998',
                    'starts_at' => CarbonImmutable::parse('2026-11-19 10:00:00', self::TZ), 'ends_at' => CarbonImmutable::parse('2026-11-19 10:30:00', self::TZ),
                    'status' => 'pending_confirmation', 'created_by' => $admin,
                ]);

                return [$schedule, $appointment];
            };
            [$old] = $make('Demo Vacunación', 'Persona Inventada');
            [, $ownAppointment] = $make('Agenda propia', 'Otra Persona');

            return [$postman, $old, $ownAppointment];
        });

        $this->artisan('demo:agenda')->assertSuccessful();

        $this->inDemoClinic(function () use ($postman, $old, $ownAppointment) {
            $suc01 = Branch::where('code', 'SUC-01')->value('id');
            $demo = Schedule::where('name', 'like', 'Demo %')->get();
            $this->assertCount(5, $demo);
            $this->assertSame([$suc01], $demo->pluck('branch_id')->unique()->values()->all());
            $this->assertNull(Schedule::find($old->id), 'La "Demo Vacunación" de la otra sucursal se elimina.');
            $this->assertSame(0, Appointment::where('branch_id', $postman->id)->where('id', '!=', $ownAppointment->id)->count());
            $this->assertNotNull(Appointment::find($ownAppointment->id), 'El turno de la agenda propia queda.');
            $this->assertTrue(Branch::find($postman->id)->is_default, 'Las sucursales no se tocan.');
        });
    }

    public function test_the_clock_is_restored_after_a_run(): void
    {
        $now = CarbonImmutable::parse(self::WEDNESDAY, self::TZ);
        $this->travelTo($now);

        $this->artisan('demo:agenda')->assertSuccessful();

        $this->assertTrue(now()->equalTo($now), 'Después de una corrida, el reloj es el de antes.');
        $this->assertTrue(CarbonImmutable::now()->equalTo($now));
    }

    /**
     * El reloj de PHP vuelve a su valor también si la generación falla a mitad de un paso con el
     * reloj corrido (finally); y la transacción no deja nada.
     */
    public function test_the_clock_is_restored_and_nothing_is_left_after_a_failure(): void
    {
        $now = CarbonImmutable::parse(self::WEDNESDAY, self::TZ);
        $this->travelTo($now);

        // El atendido se marca con el reloj corrido (20 minutos después del turno).
        $this->instance(AppointmentTransitionService::class, new class ($this->app->make(AppointmentService::class), $this->app->make(AppointmentOwnership::class)) extends AppointmentTransitionService
        {
            public function attend(Appointment $appointment): Appointment
            {
                throw new \RuntimeException('Falla simulada en el atendido.');
            }
        });

        $this->artisan('demo:agenda')->expectsOutputToContain('Falla simulada')->assertFailed();

        $this->assertTrue(now()->equalTo($now), 'Después de una falla, el reloj es el de antes.');
        $this->assertTrue(CarbonImmutable::now()->equalTo($now));
        $this->assertSame(0, DB::table('appointments')->count(), 'La transacción no deja nada a medias.');
        $this->assertSame(0, DB::table('schedules')->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:agenda')
            ->expectsOutputToContain('solo corre en los entornos local y testing')
            ->assertFailed();

        $this->assertSame(0, DB::table('schedules')->count());
        $this->assertSame(0, DB::table('appointments')->count());
        $this->assertSame(0, DB::table('services')->count());
    }

    public function test_without_the_demo_clinic_it_fails_without_creating_anything(): void
    {
        DB::table('system_settings')->where('key', DemoSeeder::MARKER)->delete();

        $this->artisan('demo:agenda')
            ->expectsOutputToContain('corré primero php artisan db:seed --class=DemoSeeder')
            ->assertFailed();

        $this->assertSame(0, DB::table('schedules')->count());
    }

    /**
     * Domingo 22/11 a las 22:30 en Asunción es lunes 23 a la 01:30 en UTC: la semana es la de
     * Asunción (16 a 22), y hoy es el domingo.
     */
    public function test_the_week_is_the_one_of_asuncion_with_a_fixed_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 22:30:00', self::TZ));

        $this->artisan('demo:agenda')->assertSuccessful();

        $this->inDemoClinic(function () {
            $days = Appointment::all()->map(fn ($a) => $a->starts_at->setTimezone(self::TZ)->format('Y-m-d'))->unique();
            $this->assertSame('2026-11-16', $days->min());
            $this->assertSame('2026-11-22', $days->max());

            foreach (AppointmentStatus::cases() as $status) {
                $this->assertTrue(Appointment::where('status', $status)->exists(), "Falta {$status->value}.");
            }

            $checkedIn = Appointment::where('status', AppointmentStatus::CHECKED_IN)->get();
            $this->assertTrue($checkedIn->every(fn ($a) => $a->starts_at->setTimezone(self::TZ)->format('Y-m-d') === '2026-11-22'));
        });
    }

    /**
     * El lunes temprano la semana no tiene horas pasadas: el atendido y el ausente van en el
     * domingo anterior (la semana pasada).
     */
    public function test_on_monday_morning_the_past_statuses_go_to_the_previous_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00:00', self::TZ));

        $this->artisan('demo:agenda')->assertSuccessful();

        $this->inDemoClinic(function () {
            foreach ([AppointmentStatus::ATTENDED, AppointmentStatus::NO_SHOW] as $status) {
                $appointment = Appointment::where('status', $status)->firstOrFail();
                $this->assertSame('2026-11-15', $appointment->starts_at->setTimezone(self::TZ)->format('Y-m-d'));
            }

            $this->assertTrue(Appointment::where('status', AppointmentStatus::CHECKED_IN)->exists());
            $this->assertTrue(Appointment::where('status', AppointmentStatus::PENDING_CONFIRMATION)->exists());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return $this->inDemoClinic(fn () => [
            'schedules' => Schedule::orderBy('id')->pluck('name', 'id')->all(),
            'services' => Service::orderBy('id')->pluck('name', 'id')->all(),
            'statuses' => Appointment::selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->all(),
            'blocks' => ScheduleBlock::count(),
            'appointments' => Appointment::orderBy('starts_at')->orderBy('schedule_id')->orderBy('is_overbooked')->get()
                ->map(fn ($a) => [$a->schedule_id, $a->starts_at->toIso8601String(), $a->status->value, $a->patient_id, $a->is_overbooked])->all(),
        ]);
    }

    private function inDemoClinic(\Closure $callback): mixed
    {
        return CurrentClinic::run(Clinic::where('ruc', DemoSeeder::CLINICS[0]['ruc'])->sole(), $callback);
    }
}
