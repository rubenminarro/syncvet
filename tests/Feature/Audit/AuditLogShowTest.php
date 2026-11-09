<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PatientPhotoService;
use App\Support\CurrentClinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Detalle de un registro de auditoría (FASE 2, entrega 2.6): suma old_values,
 * new_values y user_agent. Sin contraseñas (AuditLogger las enmascara) ni rutas
 * internas: la foto del paciente se audita como "(archivo)" desde la 2.6, y el detalle
 * oculta photo_path en los registros anteriores que ya tienen la ruta. Un registro de
 * otra clínica o sin clínica → 404.
 */
class AuditLogShowTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        $this->admin = $this->userWithRole($this->clinic, 'administrador');
        Sanctum::actingAs($this->admin);
    }

    public function test_the_detail_has_the_values_the_user_agent_and_the_user(): void
    {
        $client = $this->clientIn($this->clinic, ['first_name' => 'Ana']);

        $this->withHeader('User-Agent', 'Postman/11')->patchJson("/api/clients/{$client->id}", ['first_name' => 'Ana María'])->assertOk();
        $log = AuditLog::withoutGlobalScopes()->where('auditable_type', 'client')->where('event', 'updated')->sole();

        $this->getJson("/api/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Registro de auditoría obtenido correctamente.')
            ->assertJsonPath('data.event', ['value' => 'updated', 'label' => 'Modificado'])
            ->assertJsonPath('data.auditable_type', 'client')
            ->assertJsonPath('data.auditable_id', $client->id)
            ->assertJsonPath('data.user', ['id' => $this->admin->id, 'name' => $this->admin->name])
            ->assertJsonPath('data.old_values.first_name', 'Ana')
            ->assertJsonPath('data.new_values.first_name', 'Ana María')
            ->assertJsonPath('data.user_agent', 'Postman/11');
    }

    /**
     * La contraseña nunca sale: el registro la guarda enmascarada.
     */
    public function test_the_password_is_masked(): void
    {
        $user = User::factory()->for($this->clinic)->create();

        $this->patchJson("/api/users/{$user->id}", ['password' => 'Otra-Clave-Segura-2026!'])->assertOk();
        $log = AuditLog::withoutGlobalScopes()->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->first();

        $response = $this->getJson("/api/audit-logs/{$log->id}")->assertOk()
            ->assertJsonPath('data.new_values.password', '********')
            ->assertJsonPath('data.old_values.password', '********');
        $this->assertStringNotContainsString('Otra-Clave', $response->getContent());
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    /**
     * La foto del paciente se audita como "(archivo)" (al subirla y al borrarla): la ruta no
     * se guarda ni sale.
     */
    public function test_the_photo_path_is_audited_as_a_file_and_never_as_a_path(): void
    {
        Storage::fake(PatientPhotoService::DISK);
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $photo = UploadedFile::fake()->createWithContent('foto.png', file_get_contents(base_path('tests/Fixtures/photos/foto.png')));

        $this->post("/api/patients/{$patient->id}/photo", ['photo' => $photo], ['Accept' => 'application/json'])->assertOk();
        $this->deleteJson("/api/patients/{$patient->id}/photo")->assertOk();

        $logs = AuditLog::withoutGlobalScopes()->where('auditable_type', 'patient')->where('auditable_id', $patient->id)->where('event', 'updated')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([null, SystemSetting::AUDIT_FILE], [$logs[0]->old_values['photo_path'], $logs[0]->new_values['photo_path']]);
        $this->assertSame([SystemSetting::AUDIT_FILE, null], [$logs[1]->old_values['photo_path'], $logs[1]->new_values['photo_path']]);

        foreach (DB::table('audit_logs')->get() as $row) {
            $this->assertStringNotContainsString('clinics/', stripslashes((string) $row->old_values . $row->new_values));
        }

        $this->getJson("/api/audit-logs/{$logs[0]->id}")->assertOk()->assertJsonPath('data.new_values.photo_path', SystemSetting::AUDIT_FILE);
    }

    /**
     * Un registro anterior a la 2.6, que ya tiene la ruta de la foto: el detalle la oculta.
     * El logo nunca se guardó con su ruta (R-041).
     */
    public function test_an_old_log_with_a_photo_path_shows_it_as_a_file(): void
    {
        $path = "clinics/{$this->clinic->id}/patients/1/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.png";
        $log = $this->log(['photo_path' => null, 'name' => 'Firulais'], ['photo_path' => $path, 'name' => 'Firulais']);

        // MySQL guarda el JSON con las claves en otro orden: se comparan sin orden.
        $response = $this->getJson("/api/audit-logs/{$log}")->assertOk();
        $this->assertEqualsCanonicalizing(['photo_path' => null, 'name' => 'Firulais'], $response->json('data.old_values'));
        $this->assertEqualsCanonicalizing(['photo_path' => SystemSetting::AUDIT_FILE, 'name' => 'Firulais'], $response->json('data.new_values'));
        $this->assertStringNotContainsString('clinics', stripslashes($response->getContent()));

        $logo = CurrentClinic::run($this->clinic, fn () => SystemSetting::create(['key' => 'logo_path', 'value' => "clinics/{$this->clinic->id}/logo/x.png"]));
        $logoLog = AuditLog::withoutGlobalScopes()->where('auditable_type', 'system_setting')->where('auditable_id', $logo->id)->sole();
        $this->getJson("/api/audit-logs/{$logoLog->id}")->assertOk()->assertJsonPath('data.new_values.value', SystemSetting::AUDIT_FILE);
    }

    /**
     * Un registro de otra clínica, o sin clínica (un login fallido con un email
     * desconocido), no existe para esta clínica.
     */
    public function test_a_log_of_another_clinic_or_without_clinic_is_not_found(): void
    {
        $other = $this->createClinic();
        $foreign = CurrentClinic::run($other, fn () => AuditLog::create(['clinic_id' => $other->id, 'event' => AuditEvent::LOGIN, 'created_at' => now()])->id);
        $withoutClinic = DB::table('audit_logs')->insertGetId(['clinic_id' => null, 'event' => 'login_failed', 'new_values' => json_encode(['email' => 'x@example.com']), 'created_at' => now()]);

        foreach ([$foreign, $withoutClinic, 999999] as $id) {
            $this->assertErrorCode($this->getJson("/api/audit-logs/{$id}"), 404, 'RESOURCE_NOT_FOUND');
        }
    }

    /**
     * Sin rutas de escritura: la auditoría es inmutable.
     */
    public function test_there_are_no_write_routes(): void
    {
        $log = $this->log(null, ['name' => 'x']);

        foreach (['post' => '/api/audit-logs', 'patch' => "/api/audit-logs/{$log}", 'put' => "/api/audit-logs/{$log}", 'delete' => "/api/audit-logs/{$log}"] as $method => $uri) {
            $this->assertErrorCode($this->json($method, $uri), 405, 'METHOD_NOT_ALLOWED');
        }

        $this->assertNotNull(AuditLog::withoutGlobalScopes()->find($log));
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    private function log(?array $old, ?array $new): int
    {
        return CurrentClinic::run($this->clinic, fn () => AuditLog::create([
            'clinic_id' => $this->clinic->id,
            'event' => AuditEvent::UPDATED,
            'auditable_type' => 'patient',
            'auditable_id' => 1,
            'user_id' => $this->admin->id,
            'old_values' => $old,
            'new_values' => $new,
            'created_at' => now(),
        ])->id);
    }
}
