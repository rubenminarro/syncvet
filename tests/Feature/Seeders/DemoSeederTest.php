<?php

namespace Tests\Feature\Seeders;

use App\Enums\AuditEvent;
use App\Models\Clinic;
use App\Models\User;
use App\Services\ClinicService;
use Database\Seeders\DatabaseSeeder;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\DataProvider;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * DemoSeeder (entrega 0.4): dos clínicas con un usuario por rol, clientes y
 * pacientes; solo en local y testing, idempotente y con la contraseña de
 * DEMO_PASSWORD. La contraseña de los tests se genera en cada uno: nunca está
 * escrita en el código.
 */
class DemoSeederTest extends TestCase
{
    use InteractsWithAuth;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->password = 'Demo#' . Str::random(8) . random_int(10, 99);
        config(['demo.password' => $this->password]);
    }

    public function test_it_creates_two_clinics_with_a_user_per_role_clients_and_patients(): void
    {
        $this->runSeeder();

        $this->assertSame(['Clínica A', 'Clínica B'], Clinic::orderBy('name')->pluck('name')->all());

        foreach (DemoSeeder::CLINICS as $data) {
            $clinic = Clinic::where('ruc', $data['ruc'])->sole();

            foreach ($data['users'] as $role => [$email]) {
                $user = User::where('email', $email)->sole();
                $this->assertSame($clinic->id, $user->clinic_id, $email);
                $this->assertSame([$role], $user->getRoleNames()->all(), $email);
                $this->assertNotNull($user->current_branch_id, $email);
            }

            $vet = User::where('email', $data['users']['veterinario'][0])->sole();
            $ficha = DB::table('veterinarians')->where(['clinic_id' => $clinic->id, 'user_id' => $vet->id])->sole();
            $this->assertSame(['VET-000001', $data['license']], [$ficha->code, $ficha->license_number]);

            $patients = array_sum(array_map(fn (array $client) => count($client['patients']), $data['clients']));
            $this->assertSame(count($data['clients']), DB::table('clients')->where('clinic_id', $clinic->id)->count());
            $this->assertSame($patients, DB::table('patients')->where('clinic_id', $clinic->id)->count());
            // Por PatientService: códigos de la serie de la clínica, desde PAC-000001.
            $this->assertSame('PAC-000001', DB::table('patients')->where('clinic_id', $clinic->id)->orderBy('id')->value('code'));
        }
    }

    public function test_every_demo_user_logs_in_with_demo_password(): void
    {
        $this->runSeeder();

        foreach (DemoSeeder::CLINICS as $data) {
            foreach ($data['users'] as $role => [$email]) {
                $this->login($email, $this->password)
                    ->assertOk()
                    ->assertJsonPath('data.user.clinic.name', $data['name'])
                    ->assertJsonPath('data.user.roles', [$role]);
            }
        }
    }

    public function test_the_password_comes_from_demo_password(): void
    {
        $this->runSeeder();
        $first = $this->password;

        // Otra base, otra contraseña: los usuarios nuevos toman la de DEMO_PASSWORD.
        $this->clearDemoData();
        $this->password = 'Otra#' . Str::random(8) . random_int(10, 99);
        config(['demo.password' => $this->password]);
        $this->runSeeder();

        foreach (User::all() as $user) {
            $this->assertTrue(Hash::check($this->password, $user->password), $user->email);
            $this->assertFalse(Hash::check($first, $user->password), $user->email);
        }
    }

    public function test_running_it_twice_creates_nothing_new(): void
    {
        $this->runSeeder();
        $before = $this->counts();

        $this->runSeeder();

        $this->assertSame($before, $this->counts());
    }

    public function test_it_refuses_outside_local_and_testing(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            try {
                $this->runSeeder();
                $this->fail("El DemoSeeder corrió en {$environment}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('solo corre en los entornos local y testing', $e->getMessage());
            }
        }

        $this->assertSame(0, Clinic::count());
    }

    public function test_it_runs_in_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->runSeeder();

        $this->assertSame(2, Clinic::count());
    }

    public function test_it_refuses_without_demo_password(): void
    {
        foreach ([null, ''] as $value) {
            config(['demo.password' => $value]);

            try {
                $this->runSeeder();
                $this->fail('El DemoSeeder corrió sin DEMO_PASSWORD.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Definí DEMO_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, Clinic::count());
    }

    public function test_it_refuses_a_demo_password_that_breaks_the_rule(): void
    {
        $weak = 'debil' . random_int(10, 99);
        config(['demo.password' => $weak]);

        try {
            $this->runSeeder();
            $this->fail('El DemoSeeder aceptó una contraseña débil.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('La contraseña debe tener al menos 12 caracteres.', $e->getMessage());
            $this->assertStringNotContainsString($weak, $e->getMessage());
        }

        $this->assertSame(0, Clinic::count());
    }

    public function test_it_refuses_a_demo_password_found_in_known_breaches(): void
    {
        $this->compromisedPasswords = [$this->password];

        try {
            $this->runSeeder();
            $this->fail('El DemoSeeder aceptó una contraseña filtrada.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('aparece en filtraciones de datos conocidas', $e->getMessage());
            $this->assertStringNotContainsString($this->password, $e->getMessage());
        }

        $this->assertSame(0, Clinic::count());
    }

    public function test_the_password_never_reaches_the_audit(): void
    {
        $this->runSeeder();

        $audit = DB::table('audit_logs')->get()->toJson();
        $this->assertStringContainsString('recepcion-a@example.com', $audit);
        $this->assertStringNotContainsString($this->password, $audit);

        // Ni siquiera el hash: cada alta de usuario guarda la contraseña enmascarada.
        $userLogs = DB::table('audit_logs')->where(['event' => AuditEvent::CREATED->value, 'auditable_type' => 'user'])->get();
        $this->assertCount(6, $userLogs);
        foreach ($userLogs as $log) {
            $this->assertSame('********', json_decode($log->new_values, true)['password']);
        }
    }

    /**
     * Cada clínica se siembra en una transacción: si algo falla (aquí, el rol
     * veterinario no existe), no queda la clínica a medias.
     */
    public function test_a_failure_halfway_leaves_no_half_seeded_clinic(): void
    {
        $this->app->make(RolesAndPermissionsSeeder::class)->__invoke();
        Role::where('name', 'veterinario')->delete();
        // Sin volver a crear el rol: el DemoSeeder corre RolesAndPermissionsSeeder, que lo recrearía.
        $this->app->instance(RolesAndPermissionsSeeder::class, new class extends RolesAndPermissionsSeeder
        {
            public function run(): void
            {
            }
        });

        try {
            $this->runSeeder();
            $this->fail('El DemoSeeder debía fallar sin el rol veterinario.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('veterinario', $e->getMessage());
        }

        $this->assertSame(0, Clinic::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('clients')->count());
    }

    /**
     * El DemoSeeder no es parte de la siembra de una instalación (migrate --seed).
     */
    public function test_the_database_seeder_does_not_create_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Clinic::count());
    }

    /**
     * Exactamente local y testing (R-010): cualquier otro entorno, aunque parezca de
     * desarrollo, se rechaza.
     */
    public function test_it_runs_only_in_local_and_testing(): void
    {
        $this->assertSame(['local', 'testing'], DemoSeeder::ENVIRONMENTS);

        foreach (['production', 'staging', 'demo', 'development', 'dev', 'qa', 'preprod', 'Local', 'local-demo'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            try {
                $this->runSeeder();
                $this->fail("El DemoSeeder corrió en {$environment}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('solo corre en los entornos local y testing', $e->getMessage(), $environment);
            }
        }

        $this->assertSame(0, Clinic::count());
    }

    /**
     * config/demo.php no tiene una contraseña por defecto: sin DEMO_PASSWORD en el
     * entorno, el valor es nulo (y el seeder se niega).
     */
    public function test_demo_password_has_no_default_in_the_config(): void
    {
        $property = new \ReflectionProperty(Env::class, 'repository');
        $original = $property->getValue();
        $property->setValue(null, RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->immutable()->make());

        try {
            $config = require config_path('demo.php');
        } finally {
            $property->setValue(null, $original);
        }

        $this->assertNull($config['password']);
    }

    /**
     * Un email del seeder que ya es de un usuario de otra clínica: falla con un
     * mensaje claro y la clínica de demostración no queda a medias (R-010).
     */
    #[DataProvider('emailsOfAnotherClinic')]
    public function test_it_fails_when_a_demo_email_belongs_to_another_clinic(string $email, bool $deleted, int $failing): void
    {
        $other = $this->otherClinicWithUser($email, $deleted);

        try {
            $this->runSeeder();
            $this->fail('El DemoSeeder usó un email de otra clínica.');
        } catch (RuntimeException $e) {
            $this->assertSame("El email {$email} ya es de un usuario de otra clínica: el DemoSeeder no lo usa ni lo modifica.", $e->getMessage());
        }

        // La clínica que falla no queda (ni a medias); el usuario de la otra, intacto y sin ficha.
        $this->assertFalse(Clinic::where('ruc', DemoSeeder::CLINICS[$failing]['ruc'])->exists());
        $user = User::withTrashed()->where('email', $email)->sole();
        $this->assertSame(0, DB::table('veterinarians')->where('user_id', $user->id)->count());
        $this->assertSame([$other->id, ['administrador']], [$user->clinic_id, $user->getRoleNames()->all()]);
    }

    public static function emailsOfAnotherClinic(): array
    {
        return [
            'veterinario' => ['vet-a@example.com', false, 0],
            'veterinario eliminado' => ['vet-a@example.com', true, 0],
            'recepcionista de la B' => ['recepcion-b@example.com', false, 1],
            'administrador' => ['admin-a@example.com', false, 0],
            'administrador eliminado' => ['admin-a@example.com', true, 0],
        ];
    }

    /**
     * Una clínica con el RUC de una de demostración pero sin la marca: el seeder no la
     * adopta ni le agrega nada.
     */
    public function test_it_fails_on_a_clinic_with_a_demo_ruc_that_is_not_demo(): void
    {
        $ruc = DemoSeeder::CLINICS[0]['ruc'];
        $real = $this->createClinic(['ruc' => $ruc, 'ruc_dv' => (string) ClinicService::rucCheckDigit($ruc)]);

        try {
            $this->runSeeder();
            $this->fail('El DemoSeeder adoptó una clínica que no es de demostración.');
        } catch (RuntimeException $e) {
            $this->assertSame("Ya hay una clínica con el RUC {$ruc} que no es de demostración: el DemoSeeder no la usa ni la modifica.", $e->getMessage());
        }

        $this->assertSame(0, DB::table('users')->where('clinic_id', $real->id)->count());
        $this->assertSame(0, DB::table('clients')->where('clinic_id', $real->id)->count());
        $this->assertSame(0, DB::table('system_settings')->where('clinic_id', $real->id)->count());
    }

    /**
     * Las clínicas de demostración llevan la marca y RUC ficticios con dígito válido;
     * los clientes empresa, un RUC con su dígito correcto.
     */
    public function test_demo_clinics_are_marked_and_use_fictitious_rucs(): void
    {
        $this->runSeeder();

        foreach (DemoSeeder::CLINICS as $data) {
            $clinic = Clinic::where('ruc', $data['ruc'])->sole();

            $this->assertStringStartsWith('999999', $clinic->ruc, 'RUC ficticio');
            $this->assertSame((string) ClinicService::rucCheckDigit($clinic->ruc), $clinic->ruc_dv);
            $this->assertSame('1', DB::table('system_settings')->where(['clinic_id' => $clinic->id, 'key' => DemoSeeder::MARKER])->value('value'));

            foreach ($data['clients'] as $client) {
                if ($client['kind'] === 'company') {
                    [$ruc, $dv] = explode('-', $client['document_number']);
                    $this->assertSame(ClinicService::rucCheckDigit($ruc), (int) $dv, $client['document_number']);
                }
            }
        }

        // Correrlo otra vez reconoce sus clínicas por la marca.
        $this->runSeeder();
        $this->assertSame(2, Clinic::count());
    }

    private function otherClinicWithUser(string $email, bool $deleted): Clinic
    {
        $clinic = app(ClinicService::class)->create(
            ['name' => 'Otra', 'legal_name' => 'Otra S.A.', 'ruc' => '99999911', 'ruc_dv' => '7'],
            ['email' => $email, 'first_name' => 'Otra', 'last_name' => 'Persona', 'password' => 'Otra#Clave2026']
        );

        if ($deleted) {
            User::where('email', $email)->sole()->delete();
        }

        return $clinic;
    }

    private function runSeeder(): void
    {
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (['clinics', 'users', 'branches', 'warehouses', 'veterinarians', 'clients', 'patients', 'model_has_roles', 'branch_user', 'document_sequences', 'species', 'breeds', 'roles', 'permissions'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function clearDemoData(): void
    {
        foreach (['patients', 'clients', 'veterinarians', 'document_sequences', 'system_settings', 'model_has_roles', 'branch_user', 'audit_logs'] as $table) {
            DB::table($table)->delete();
        }

        DB::table('users')->update(['current_branch_id' => null]);
        foreach (['users', 'warehouses', 'branches', 'clinics'] as $table) {
            DB::table($table)->delete();
        }
    }
}
