<?php

namespace Tests\Feature\Console;

use App\Console\Commands\PostmanCleanupCommand;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ScheduleHour;
use App\Models\Service;
use App\Models\Species;
use App\Models\User;
use App\Models\Veterinarian;
use App\Models\Warehouse;
use App\Services\PatientPhotoService;
use App\Support\CurrentClinic;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\InteractsWithSchedules;
use Tests\Concerns\InteractsWithServices;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * php artisan postman:cleanup (cierre de la F3 del frontend): borra lo que dejó una corrida de las colecciones de Postman en
 * la clínica de la demo, reconocido por su número, y nada más.
 */
class PostmanCleanupCommandTest extends TestCase
{
    use InteractsWithAppointments, InteractsWithSchedules, InteractsWithServices, InteractsWithVeterinarians;

    private const RUN = '123456';

    /** Comparte los primeros dígitos con RUN: la limpieza toma el número entero. */
    private const OTHER_RUN = '123457';

    private Clinic $demo;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'Demo#' . Str::random(8) . random_int(10, 99)]);
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
        $this->demo = Clinic::where('ruc', DemoSeeder::CLINICS[0]['ruc'])->sole();
        $this->branch = CurrentClinic::run($this->demo, fn () => Branch::where('is_default', true)->sole());
        $this->freezeToday();
        Storage::fake(PatientPhotoService::DISK);
        $this->allowHere();
    }

    /**
     * El comando solo corre con APP_ENV=local y en la base syncvet: los tests (testing, SQLite en memoria) lo habilitan a
     * propósito; test_it_refuses_outside_local_and_the_development_database prueba cada negativa.
     */
    private function allowHere(string $environment = PostmanCleanupCommand::ENVIRONMENT, string $database = PostmanCleanupCommand::DATABASE): void
    {
        $this->app['env'] = $environment;
        DB::connection()->setDatabaseName($database);
    }

    /**
     * Lo que deja una corrida de la colección de turnos: su usuario veterinario con ficha, su servicio, su agenda con tramos,
     * un bloqueo y turnos, dos clientes (uno con un paciente con foto) y turnos de ellos.
     *
     * @return array<string, int>
     */
    private function postmanRun(string $run): array
    {
        $user = $this->veterinarianUser($this->demo, [$this->branch], ['email' => "turnos{$run}@mailinator.com"]);
        $user->createToken('postman');
        $vet = $this->veterinarianOf($user, ['license_number' => "MP-{$run}"]);
        $service = $this->serviceIn($this->demo, ['name' => "Consulta turnos {$run}"]);
        $schedule = $this->scheduleIn($this->branch, ['name' => "Turnos Postman {$run} hoy", 'veterinarian_id' => $vet->id, 'service_id' => $service->id]);
        $this->hoursFor($schedule, array_map(fn (int $weekday) => [$weekday, '08:00', '20:00'], range(1, 7)));
        $block = $this->blockFor($schedule, ['starts_at' => $this->at('15:00'), 'ends_at' => $this->at('16:00')]);
        $client = $this->clientIn($this->demo, ['email' => "rosa.turnos.{$run}@mailinator.com"]);
        $patient = $this->patientOf($client, ['microchip' => "CHIP{$run}"]);
        // photo_path no es asignable (solo lo cambia PatientPhotoService).
        $this->inDemo(fn () => $patient->forceFill(['photo_path' => "{$this->demo->id}/foto-{$run}.jpg"])->saveQuietly());
        Storage::disk(PatientPhotoService::DISK)->put("{$this->demo->id}/foto-{$run}.jpg", 'jpg');
        [$species, $breed] = CurrentClinic::run($this->demo, function () use ($run) {
            $species = Species::create(['name' => "Hurón {$run}"]);

            return [$species, Breed::create(['species_id' => $species->id, 'name' => "Albino {$run}"])];
        });

        $this->actAsReception($this->demo);
        $this->booked($schedule, ['starts_at' => $this->at('10:00')]);
        $withPatient = $this->booked($schedule, ['starts_at' => $this->at('11:00'), 'patient_id' => $patient->id, 'client_id' => $client->id, 'contact_name' => null, 'contact_phone' => null]);
        // Una consulta del turno con paciente (FASE 4, entrega 4.1): se borra antes que el turno (restrictOnDelete).
        $consultation = DB::table('consultations')->insertGetId([
            'clinic_id' => $this->demo->id, 'code' => "CON-{$run}", 'branch_id' => $this->branch->id,
            'patient_id' => $patient->id, 'client_id' => $client->id, 'appointment_id' => $withPatient,
            'veterinarian_id' => $vet->id, 'consulted_at' => now(), 'status' => 'open', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Con una enmienda (entrega 4.2): inmutable y restrictOnDelete, se borra antes que la consulta.
        DB::table('consultation_amendments')->insert([
            'clinic_id' => $this->demo->id, 'consultation_id' => $consultation, 'content' => 'Corrección', 'reason' => 'Error',
            'created_by' => $user->id, 'created_at' => now(),
        ]);

        return [
            'consultation' => $consultation, 'user' => $user->id, 'vet' => $vet->id, 'service' => $service->id, 'schedule' => $schedule->id, 'block' => $block->id,
            'client' => $client->id, 'patient' => $patient->id, 'species' => $species->id, 'breed' => $breed->id,
        ];
    }

    public function test_it_deletes_what_a_run_left_and_nothing_else(): void
    {
        $this->artisan('demo:agenda')->assertSuccessful();
        $demoAppointments = $this->inDemo(fn () => Appointment::count());
        $run = $this->postmanRun(self::RUN);
        $other = $this->postmanRun(self::OTHER_RUN);
        // Datos ajenos con el número dentro de otro texto: no son de la corrida.
        $lookalikeSchedule = $this->scheduleIn($this->branch, ['name' => 'Agenda 123456b', 'veterinarian_id' => $this->veterinarianIn($this->branch)->id]);
        $lookalikeClient = $this->clientIn($this->demo, ['email' => 'ana.x123456b@mailinator.com']);
        $lookalikeService = $this->serviceIn($this->demo, ['name' => 'Servicio A123456']);
        // Lo que la API ya dio de baja lleva el sufijo de DeletedSuffix en el email y en la matrícula.
        $deletedClient = $this->clientIn($this->demo, ['email' => 'marta.benitez.' . self::RUN . '@mailinator.com//deleted_9_1795194708']);
        $this->inDemo(fn () => $deletedClient->delete());
        $deletedVet = $this->veterinarianOf($this->veterinarianUser($this->demo, [$this->branch]), ['license_number' => 'MP-' . self::RUN . '//deleted_8_1795194786']);

        $this->artisan('postman:cleanup', ['runs' => [self::RUN]])
            ->expectsOutputToContain('Corrida 123456: 1 enmiendas, 1 consultas, 2 turnos, 1 bloqueos, 7 tramos, 1 agendas, 1 pacientes, 2 clientes, 2 fichas, 1 servicios, 1 razas, 1 especies, 0 sucursales, 0 depósitos, 1 usuarios dados de baja.')
            ->assertSuccessful();

        $this->inDemo(fn () => $this->assertNull(Client::withTrashed()->find($deletedClient->id)));
        $this->inDemo(fn () => $this->assertNull(Veterinarian::withTrashed()->find($deletedVet->id)));

        $this->inDemo(function () use ($run, $other, $demoAppointments, $lookalikeSchedule, $lookalikeClient, $lookalikeService) {
            $this->assertNotNull(Schedule::find($lookalikeSchedule->id));
            $this->assertNotNull(Client::find($lookalikeClient->id));
            $this->assertNotNull(Service::find($lookalikeService->id));
            $this->assertNull(Schedule::find($run['schedule']));
            $this->assertSame(0, DB::table('consultations')->where('id', $run['consultation'])->count());
            $this->assertSame(0, Appointment::where('schedule_id', $run['schedule'])->count());
            $this->assertNull(ScheduleBlock::find($run['block']));
            $this->assertSame(0, ScheduleHour::where('schedule_id', $run['schedule'])->count());
            $this->assertNull(Client::withTrashed()->find($run['client']));
            $this->assertNull(Patient::withTrashed()->find($run['patient']));
            $this->assertNull(Veterinarian::withTrashed()->find($run['vet']));
            $this->assertNull(Service::withTrashed()->find($run['service']));
            $this->assertNull(Species::withTrashed()->find($run['species']));
            $this->assertNull(Breed::withTrashed()->find($run['breed']));
            Storage::disk(PatientPhotoService::DISK)->assertMissing("{$this->demo->id}/foto-" . self::RUN . '.jpg');

            // La otra corrida y la demo, intactas.
            $this->assertNotNull(Schedule::find($other['schedule']));
            $this->assertSame(1, DB::table('consultations')->where('id', $other['consultation'])->count());
            $this->assertSame(2, Appointment::where('schedule_id', $other['schedule'])->count());
            $this->assertNotNull(Patient::find($other['patient']));
            Storage::disk(PatientPhotoService::DISK)->assertExists("{$this->demo->id}/foto-" . self::OTHER_RUN . '.jpg');
            $this->assertSame($demoAppointments + 2, Appointment::count());
            $this->assertSame(5, Schedule::where('name', 'like', 'Demo %')->count());
        });

        // El usuario queda dado de baja y sin tokens (la auditoría lo referencia); el de la otra corrida, igual que antes.
        $user = User::withTrashed()->findOrFail($run['user']);
        $this->assertTrue($user->trashed());
        // Como la API (UserService): el email con el sufijo que lo libera, para que otra corrida con el mismo número lo use.
        $this->assertMatchesRegularExpression('#^turnos123456@mailinator\.com//deleted_\d+_\d+$#', $user->email);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse(User::findOrFail($other['user'])->trashed());
        $this->assertSame(1, User::findOrFail($other['user'])->tokens()->count());
    }

    public function test_dry_run_counts_without_deleting_anything(): void
    {
        $run = $this->postmanRun(self::RUN);
        $tables = fn () => collect(['consultation_amendments', 'consultations', 'appointments', 'schedules', 'clients', 'patients', 'services', 'veterinarians', 'users'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
        $before = $tables();

        $this->artisan('postman:cleanup', ['runs' => [self::RUN], '--dry-run' => true])
            ->expectsOutputToContain('Sin borrar (--dry-run). Corrida 123456: 1 enmiendas, 1 consultas, 2 turnos,')
            ->assertSuccessful();

        $this->assertSame($before, $tables());
        $this->assertFalse(User::findOrFail($run['user'])->trashed());
        Storage::disk(PatientPhotoService::DISK)->assertExists("{$this->demo->id}/foto-" . self::RUN . '.jpg');
    }

    public function test_it_leaves_the_demo_users_untouched(): void
    {
        $before = User::whereIn('email', ['admin-a@example.com', 'recepcion-a@example.com', 'vet-a@example.com'])
            ->orderBy('id')->get(['id', 'updated_at', 'deleted_at', 'current_branch_id'])->toArray();
        $this->postmanRun(self::RUN);

        $this->artisan('postman:cleanup', ['runs' => [self::RUN]])->assertSuccessful();

        $this->assertSame($before, User::whereIn('email', ['admin-a@example.com', 'recepcion-a@example.com', 'vet-a@example.com'])
            ->orderBy('id')->get(['id', 'updated_at', 'deleted_at', 'current_branch_id'])->toArray());
    }

    public function test_a_run_branch_is_deleted_unless_someone_else_uses_it(): void
    {
        [$free, $used] = $this->inDemo(function () {
            $free = Branch::create(['code' => 'SUC-AB12CD-X', 'name' => 'Otra sucursal AB12CD', 'establishment' => '091']);
            Warehouse::create(['branch_id' => $free->id, 'code' => 'DEP-AB12CD-X', 'name' => 'Depósito AB12CD']);
            $used = Branch::create(['code' => 'SUC-AB12CD-R', 'name' => 'Recepción AB12CD', 'establishment' => '092']);
            Warehouse::create(['branch_id' => $used->id, 'code' => 'DEP-AB12CD-R', 'name' => 'Depósito de la usada']);
            // La colección de sucursales reusa una sucursal fija (SUC-PM): le crea el depósito DEP-<corrida>-2, lo marca como
            // el por defecto e inactiva el que tenía.
            $fixed = Branch::create(['code' => 'SUC-PM', 'name' => 'Sucursal fija', 'establishment' => '093']);
            Warehouse::create(['branch_id' => $fixed->id, 'code' => 'DEP-PM', 'name' => 'Principal', 'is_default' => false, 'is_active' => false]);
            Warehouse::create(['branch_id' => $fixed->id, 'code' => 'DEP-AB12CD-2', 'name' => 'Farmacia AB12CD', 'is_default' => true]);

            return [$free, $used];
        });
        User::where('email', 'admin-a@example.com')->sole()->branches()->attach($used->id);

        $this->artisan('postman:cleanup', ['runs' => ['ab12cd']])
            ->expectsOutputToContain('Sucursales sin tocar (las usa alguien que no es de la corrida): SUC-AB12CD-R.')
            ->assertSuccessful();

        $this->inDemo(function () use ($free, $used) {
            $this->assertNull(Branch::find($free->id));
            $this->assertSame(0, Warehouse::where('branch_id', $free->id)->count());
            $this->assertNotNull(Branch::find($used->id));
            $this->assertSame(['DEP-AB12CD-R'], Warehouse::where('code', 'like', 'DEP-AB12CD-%')->pluck('code')->all());
            // SUC-PM no queda sin depósito por defecto: vuelve a ser el suyo, activo.
            $home = Warehouse::where('code', 'DEP-PM')->sole();
            $this->assertTrue($home->is_default);
            $this->assertTrue($home->is_active);
        });
    }

    public function test_tokens_since_revokes_only_the_demo_users_tokens_created_since_then(): void
    {
        $admin = User::where('email', 'admin-a@example.com')->sole();
        $updatedAt = $admin->updated_at;
        $old = $admin->createToken('api');
        $this->travel(5)->minutes();
        $since = now()->toImmutable();
        $new = $admin->createToken('api');
        $otherUser = $this->userIn($this->demo);
        $notDemo = $otherUser->createToken('api');

        $this->artisan('postman:cleanup', ['runs' => [self::RUN], '--tokens-since' => $since->utc()->toIso8601ZuluString(), '--dry-run' => true])
            ->expectsOutputToContain('Tokens de los usuarios de la demo desde el')
            ->assertSuccessful();
        $this->assertNotNull($new->accessToken->fresh());

        $this->artisan('postman:cleanup', ['runs' => [self::RUN], '--tokens-since' => $since->utc()->toIso8601ZuluString()])
            ->expectsOutputToContain(': 1.')
            ->assertSuccessful();

        $this->assertNull($new->accessToken->fresh());
        $this->assertNotNull($old->accessToken->fresh());
        $this->assertNotNull($notDemo->accessToken->fresh());
        $this->assertEquals($updatedAt, $admin->fresh()->updated_at);

        $this->artisan('postman:cleanup', ['runs' => [self::RUN], '--tokens-since' => 'ayer'])->assertFailed();
    }

    public function test_it_refuses_a_short_run_or_another_environment_without_touching_anything(): void
    {
        $run = $this->postmanRun(self::RUN);

        $this->artisan('postman:cleanup', ['runs' => ['1234']])->assertFailed();
        $this->artisan('postman:cleanup', ['runs' => ['12%45']])->assertFailed();
        // Una palabra sola no es una corrida: "Consultorio" tomaría las agendas "Demo Consultorio 1" y "2".
        $this->artisan('demo:agenda')->assertSuccessful();
        $this->artisan('postman:cleanup', ['runs' => ['Consultorio']])->assertFailed();
        $this->inDemo(fn () => $this->assertSame(2, Schedule::where('name', 'like', 'Demo Consultorio%')->count()));

        $this->inDemo(fn () => $this->assertNotNull(Schedule::find($run['schedule'])));
    }

    public function test_it_refuses_outside_local_and_the_development_database(): void
    {
        $run = $this->postmanRun(self::RUN);
        $tables = fn () => collect(['consultation_amendments', 'consultations', 'appointments', 'schedules', 'clients', 'patients', 'services', 'veterinarians', 'personal_access_tokens'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
        $before = $tables();
        $cases = [
            ['testing', 'syncvet'], ['production', 'syncvet'], ['staging', 'syncvet'],
            ['local', 'syncvet_e2e'], ['local', 'syncvet_test_mysql'], ['local', ':memory:'],
        ];

        foreach ($cases as [$environment, $database]) {
            $this->allowHere($environment, $database);
            $this->artisan('postman:cleanup', ['runs' => [self::RUN], '--tokens-since' => '2000-01-01T00:00:00Z'])
                ->expectsOutputToContain('solo corre con APP_ENV=local y en la base de desarrollo (syncvet)')
                ->assertFailed();
        }

        $this->assertSame($before, $tables());
        $this->assertFalse(User::findOrFail($run['user'])->trashed());

        // Con los dos en regla, sí.
        $this->allowHere();
        $this->artisan('postman:cleanup', ['runs' => [self::RUN]])->assertSuccessful();
        $this->assertTrue(User::withTrashed()->findOrFail($run['user'])->trashed());
    }

    public function test_it_only_touches_the_demo_clinic(): void
    {
        $other = $this->createClinic();
        $foreign = $this->clientIn($other, ['email' => 'rosa.turnos.' . self::RUN . '@mailinator.com']);

        $this->artisan('postman:cleanup', ['runs' => [self::RUN]])->assertSuccessful();

        $this->assertNotNull(CurrentClinic::run($other, fn () => Client::find($foreign->id)));
        $this->assertSame(1, DB::table('clients')->where('id', $foreign->id)->count());
    }

    private function inDemo(\Closure $callback): mixed
    {
        return CurrentClinic::run($this->demo, $callback);
    }
}
