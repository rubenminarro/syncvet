<?php

namespace Tests\Feature\Settings;

use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Support\CurrentClinic;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Configuración de la clínica (FASE 2, entrega 2.5; P11, P13 y preguntas 23 y 24):
 * system-settings para leer (los tres roles) y editar (el administrador) los datos de
 * la clínica y las claves cerradas de SystemSettingKey. null borra el valor; una clave
 * fuera de la lista, también logo_path, → 422; el RUC no se edita.
 */
class SystemSettingsTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAuth;

    private const KEYS = ['contact_email', 'contact_phone', 'website', 'document_footer'];

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic(['name' => 'Veterinaria Norte', 'legal_name' => 'Veterinaria Norte S.A.', 'ruc' => '99999901', 'ruc_dv' => '2']);
    }

    public static function roles(): array
    {
        return ['administrador' => ['administrador'], 'recepcionista' => ['recepcionista'], 'veterinario' => ['veterinario']];
    }

    #[DataProvider('roles')]
    public function test_the_three_roles_read_the_settings(string $role): void
    {
        $this->setting('contact_phone', '+595981000111');
        $this->setting('logo_path', ClinicPaths::logo($this->clinic->id));
        Sanctum::actingAs($this->userWithRole($this->clinic, $role));

        $this->getJson('/api/system-settings')
            ->assertOk()
            ->assertJsonPath('message', 'Configuración de la clínica.')
            ->assertJsonPath('data.clinic', ['name' => 'Veterinaria Norte', 'legal_name' => 'Veterinaria Norte S.A.', 'ruc' => '99999901', 'ruc_dv' => '2'])
            // Todas las claves editables, null si no tienen valor; logo_path nunca sale.
            ->assertJsonPath('data.settings', ['contact_email' => null, 'contact_phone' => '+595981000111', 'website' => null, 'document_footer' => null])
            ->assertJsonPath('data.has_logo', true)
            ->assertJsonMissingPath('data.settings.logo_path')
            ->assertDontSee('clinics/' . $this->clinic->id . '/logo', false);
    }

    public function test_the_administrator_edits_the_data_and_the_keys_partially(): void
    {
        $this->setting('document_footer', 'Gracias por su visita.');
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $this->patchJson('/api/system-settings', [
            'name' => 'Veterinaria Centro',
            'settings' => [
                'contact_email' => 'Contacto@Example.com',
                'contact_phone' => '+595981222333',
                'website' => 'https://veterinaria.example.com',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Configuración actualizada correctamente.')
            ->assertJsonPath('data.clinic.name', 'Veterinaria Centro')
            // Semántica parcial: lo que no llega no cambia.
            ->assertJsonPath('data.clinic.legal_name', 'Veterinaria Norte S.A.')
            ->assertJsonPath('data.settings', [
                'contact_email' => 'contacto@example.com',
                'contact_phone' => '+595981222333',
                'website' => 'https://veterinaria.example.com',
                'document_footer' => 'Gracias por su visita.',
            ]);

        // PUT, con la misma semántica parcial (HttpVerbsTest).
        $this->putJson('/api/system-settings', ['legal_name' => 'Veterinaria Centro S.R.L.'])
            ->assertOk()
            ->assertJsonPath('data.clinic.name', 'Veterinaria Centro')
            ->assertJsonPath('data.clinic.legal_name', 'Veterinaria Centro S.R.L.')
            ->assertJsonPath('data.settings.contact_phone', '+595981222333');

        $this->assertSame('Veterinaria Centro S.R.L.', $this->clinic->fresh()->legal_name);
    }

    public function test_null_deletes_a_value_and_the_change_is_audited(): void
    {
        $this->setting('contact_phone', '+595981000111');
        $this->setting('website', 'https://norte.example.com');
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));
        $lastLog = (int) DB::table('audit_logs')->max('id');

        $this->patchJson('/api/system-settings', ['settings' => ['contact_phone' => null, 'document_footer' => 'Pie nuevo', 'website' => 'https://norte.example.com']])
            ->assertOk()
            ->assertJsonPath('data.settings', ['contact_email' => null, 'contact_phone' => null, 'website' => 'https://norte.example.com', 'document_footer' => 'Pie nuevo']);

        $this->assertSame(['document_footer', 'website'], DB::table('system_settings')->where('clinic_id', $this->clinic->id)->orderBy('key')->pluck('key')->all());
        $events = DB::table('audit_logs')->where('auditable_type', 'system_setting')->where('id', '>', $lastLog)->pluck('event')->all();
        sort($events);
        // El alta de document_footer y la baja de contact_phone; el sitio web, igual, no es un cambio.
        $this->assertSame(['created', 'deleted'], $events);
    }

    public function test_the_change_of_the_clinic_data_is_audited(): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $this->patchJson('/api/system-settings', ['name' => 'Veterinaria Centro'])->assertOk();

        $log = DB::table('audit_logs')->where('auditable_type', 'clinic')->where('auditable_id', $this->clinic->id)->where('event', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertSame(['name' => 'Veterinaria Centro'], json_decode($log->new_values, true));
    }

    #[DataProvider('readOnlyRoles')]
    public function test_the_other_roles_do_not_edit(string $role): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, $role));

        $this->assertErrorCode($this->patchJson('/api/system-settings', ['name' => 'Otra', 'settings' => ['website' => 'https://otra.example.com']]), 403, 'FORBIDDEN');

        $this->assertSame('Veterinaria Norte', $this->clinic->fresh()->name);
        $this->assertSame(0, DB::table('system_settings')->count());
    }

    public static function readOnlyRoles(): array
    {
        return ['recepcionista' => ['recepcionista'], 'veterinario' => ['veterinario']];
    }

    /**
     * Una clave fuera de la lista → 422 en esa clave, sin cambios a medias; logo_path
     * es interna y tampoco se edita.
     */
    public function test_an_unknown_key_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        foreach (['color_primario', 'logo_path'] as $key) {
            $response = $this->patchJson('/api/system-settings', ['name' => 'Veterinaria Centro', 'settings' => ['website' => 'https://x.example.com', $key => 'valor']])
                ->assertUnprocessable();

            $this->assertSame(["La clave {$key} no existe en la configuración."], $response->json('errors')["settings.{$key}"] ?? null);
        }

        $this->assertSame('Veterinaria Norte', $this->clinic->fresh()->name);
        $this->assertSame(0, DB::table('system_settings')->count());
    }

    public static function invalidValues(): array
    {
        return [
            'correo sin formato' => ['contact_email', 'no-es-un-correo', 'El correo de contacto debe tener el formato correcto.'],
            'correo de más de 150' => ['contact_email', str_repeat('a', 140) . '@example.com', 'El correo de contacto no debe tener más de 150 caracteres.'],
            'teléfono con letras' => ['contact_phone', '0981-ABC', 'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).'],
            'teléfono corto' => ['contact_phone', '+12345', 'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).'],
            'sitio sin https' => ['website', 'http://norte.example.com', 'El sitio web debe ser una dirección que empiece con https://.'],
            'sitio sin formato' => ['website', 'norte', 'El sitio web debe ser una dirección que empiece con https://.'],
            'sitio de más de 150' => ['website', 'https://' . str_repeat('a', 140) . '.com', 'El sitio web no debe tener más de 150 caracteres.'],
            'pie de más de 500' => ['document_footer', str_repeat('a', 501), 'El pie de recetas y comprobantes no debe tener más de 500 caracteres.'],
            'pie que no es texto' => ['document_footer', ['a'], 'El pie de recetas y comprobantes debe tener el formato correcto.'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_each_key_has_its_rule(string $key, mixed $value, string $message): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $response = $this->patchJson('/api/system-settings', ['settings' => [$key => $value]])->assertUnprocessable();

        $this->assertSame($message, $response->json('errors')["settings.{$key}"][0] ?? null);
    }

    public function test_the_limits_of_each_key_are_accepted(): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));
        $values = [
            'contact_email' => str_repeat('a', 138) . '@example.com',
            'contact_phone' => '981000111',
            'website' => 'https://' . str_repeat('a', 138) . '.com',
            'document_footer' => str_repeat('a', 500),
        ];

        $this->patchJson('/api/system-settings', ['settings' => $values])->assertOk()->assertJsonPath('data.settings', $values);
    }

    public function test_the_ruc_and_the_clinic_name_rules(): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $this->patchJson('/api/system-settings', ['ruc' => '99999902', 'ruc_dv' => '0', 'name' => 'A', 'legal_name' => '', 'settings' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ruc.0', 'El RUC de la clínica no se modifica desde la configuración.')
            ->assertJsonPath('errors.ruc_dv.0', 'El RUC de la clínica no se modifica desde la configuración.')
            ->assertJsonPath('errors.name.0', 'El nombre de la clínica debe tener al menos 2 caracteres.')
            ->assertJsonPath('errors.legal_name.0', 'La razón social es obligatoria.')
            ->assertJsonPath('errors.settings.0', 'La configuración debe ser una lista de claves y valores.');

        $this->patchJson('/api/system-settings', ['name' => str_repeat('a', 101), 'legal_name' => str_repeat('a', 151)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'El nombre de la clínica no debe tener más de 100 caracteres.')
            ->assertJsonPath('errors.legal_name.0', 'La razón social no debe tener más de 150 caracteres.');

        $fresh = $this->clinic->fresh();
        $this->assertSame(['99999901', '2', 'Veterinaria Norte'], [$fresh->ruc, $fresh->ruc_dv, $fresh->name]);
    }

    /**
     * Cada clínica ve y edita solo su configuración.
     */
    public function test_the_settings_of_another_clinic_are_not_seen_or_changed(): void
    {
        $other = $this->createClinic(['name' => 'Veterinaria Sur']);
        CurrentClinic::run($other, fn () => SystemSetting::create(['key' => 'website', 'value' => 'https://sur.example.com']));
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $this->getJson('/api/system-settings')->assertOk()->assertJsonPath('data.settings.website', null)->assertJsonPath('data.clinic.name', 'Veterinaria Norte');
        $this->patchJson('/api/system-settings', ['settings' => ['website' => null]])->assertOk();

        $this->assertSame('https://sur.example.com', DB::table('system_settings')->where('clinic_id', $other->id)->value('value'));
        $this->assertSame('Veterinaria Sur', $other->fresh()->name);
    }

    /**
     * Orden de bloqueo clínica → configuración: la fila de la clínica antes de leer las
     * claves. "for update" solo en MySQL.
     */
    public function test_editing_locks_the_clinic_before_reading_the_keys(): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));

        $queries = $this->queriesOf(fn () => $this->patchJson('/api/system-settings', ['settings' => ['website' => 'https://norte.example.com']])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->readOf('system_settings'), 'Se leyeron las claves antes de bloquear la clínica.');
    }

    /**
     * Corrección de la 2.5 (validación del usuario): una fila con una clave que no está en
     * SystemSettingKey (cargada sin la API) daba 500 al leer la configuración, por el cast
     * del modelo. Se ignora al leer, con un aviso en el log, y no se borra: la lectura, la
     * edición, el logo y `me` responden 200.
     */
    public function test_an_unknown_key_in_the_database_is_ignored_with_a_warning(): void
    {
        DB::table('system_settings')->insert(['clinic_id' => $this->clinic->id, 'key' => 'clave_vieja', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $this->setting('website', 'https://norte.example.com');
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));
        Log::spy();

        $this->getJson('/api/system-settings')
            ->assertOk()
            ->assertJsonPath('data.settings', ['contact_email' => null, 'contact_phone' => null, 'website' => 'https://norte.example.com', 'document_footer' => null])
            ->assertJsonPath('data.has_logo', false);
        $this->patchJson('/api/system-settings', ['settings' => ['document_footer' => 'Pie']])->assertOk()->assertJsonPath('data.settings.document_footer', 'Pie');
        $this->deleteJson('/api/system-settings/logo')->assertNotFound();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.clinic.has_logo', false);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => str_contains($message, 'desconocida') && $context === ['clinic_id' => $this->clinic->id, 'key' => 'clave_vieja']
        )->twice();
        $this->assertSame('1', DB::table('system_settings')->where('clinic_id', $this->clinic->id)->where('key', 'clave_vieja')->value('value'));
    }

    /**
     * La marca del DemoSeeder (demo_data, R-010), como la crea el seeder en la base de
     * desarrollo: es una clave interna de SystemSettingKey. No sale en la respuesta, no
     * deja avisos y no se edita por la API.
     */
    public function test_the_demo_seeder_marker_is_an_internal_key(): void
    {
        $this->assertSame('demo_data', DemoSeeder::MARKER);
        DB::table('system_settings')->insert(['clinic_id' => $this->clinic->id, 'key' => DemoSeeder::MARKER, 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->userWithRole($this->clinic, 'administrador'));
        Log::spy();

        $this->getJson('/api/system-settings')->assertOk()->assertJsonMissingPath('data.settings.demo_data')->assertDontSee('demo_data');
        Log::shouldNotHaveReceived('warning');

        $response = $this->patchJson('/api/system-settings', ['settings' => ['demo_data' => '0']])->assertUnprocessable();
        $this->assertSame(['La clave demo_data no existe en la configuración.'], $response->json('errors')['settings.demo_data'] ?? null);
        $this->assertSame('1', DB::table('system_settings')->where('clinic_id', $this->clinic->id)->where('key', 'demo_data')->value('value'));
    }

    private function setting(string $key, string $value): void
    {
        CurrentClinic::run($this->clinic, fn () => SystemSetting::create(['key' => $key, 'value' => $value]));
    }
}

/**
 * Rutas de ejemplo con el nombre que les da ClinicLogoService.
 */
final class ClinicPaths
{
    public static function logo(int $clinicId): string
    {
        return "clinics/{$clinicId}/logo/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.png";
    }
}
