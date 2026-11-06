<?php

namespace Tests\Feature\Patients;

use App\Enums\AuditEvent;
use App\Http\Requests\UploadPatientPhotoRequest;
use App\Models\Clinic;
use App\Models\Patient;
use App\Services\PatientPhotoService;
use App\Support\CurrentClinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Foto del paciente (FASE 1, entrega 1.5): patients/{patient}/photo para subir, ver
 * y borrar. JPG, PNG o WEBP de hasta 5 MB, por el contenido del archivo; más grande,
 * 413 PAYLOAD_TOO_LARGE. Disco privado con nombre al azar; el reemplazo borra la
 * anterior. Descarga con nosniff y no-store. Un fallecido también cambia la foto.
 * La respuesta muestra has_photo, nunca la ruta interna.
 *
 * Los archivos son reales (tests/Fixtures/photos), copiados a un temporal: el tipo
 * se detecta en sus bytes. UploadedFile::fake() informa el tipo según el nombre y no
 * probaría la detección. Con el rol recepcionista y repetidos con veterinario.
 */
class PatientPhotoTest extends TestCase
{
    use InteractsWithAuth;

    private const FIXTURES = __DIR__ . '/../../Fixtures/photos/';

    private Clinic $clinic;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PatientPhotoService::DISK);
        $this->clinic = $this->createClinic();
        $this->patient = $this->patientOf($this->clientIn($this->clinic));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['recepcionista' => ['recepcionista'], 'veterinario' => ['veterinario']];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function images(): array
    {
        return [
            'jpg' => ['foto.jpg', 'jpg', 'image/jpeg'],
            'png' => ['foto.png', 'png', 'image/png'],
            'webp' => ['foto.webp', 'webp', 'image/webp'],
        ];
    }

    #[DataProvider('images')]
    public function test_jpg_png_and_webp_are_saved_privately_with_a_random_name(string $fixture, string $extension, string $type): void
    {
        $this->actAs('recepcionista');

        $response = $this->upload($this->file($fixture, 'Mi Mascota Original.' . $extension))->assertOk();

        $response->assertJsonPath('data.has_photo', true)->assertJsonMissingPath('data.photo_path');
        $path = $this->photoPath();
        $this->assertMatchesRegularExpression(
            "#^clinics/{$this->clinic->id}/patients/{$this->patient->id}/[0-9a-f-]{36}\.{$extension}$#",
            $path
        );
        $this->assertStringNotContainsString('Original', $path);
        $this->assertStringNotContainsString($path, $response->getContent());
        Storage::disk(PatientPhotoService::DISK)->assertExists($path);
        $this->assertSame(file_get_contents(self::FIXTURES . $fixture), Storage::disk(PatientPhotoService::DISK)->get($path));
        $this->assertSame($type, Storage::disk(PatientPhotoService::DISK)->mimeType($path));
    }

    #[DataProvider('roles')]
    public function test_both_roles_upload_see_and_delete_the_photo(string $role): void
    {
        $this->actAs($role);

        $this->upload($this->file('foto.png'))->assertOk()->assertJsonPath('data.has_photo', true);
        $this->getJson($this->url())->assertOk();
        $this->getJson("/api/patients/{$this->patient->id}")->assertJsonPath('data.has_photo', true);
        $this->getJson('/api/patients')->assertJsonPath('data.0.has_photo', true);
        $this->deleteJson($this->url())->assertOk()->assertJsonPath('data.has_photo', false);
    }

    /**
     * La extensión sale del contenido: un PNG llamado .jpg se guarda como .png.
     */
    public function test_the_extension_comes_from_the_content(): void
    {
        $this->actAs('recepcionista');

        $this->upload($this->file('foto.png', 'foto.jpg'))->assertOk();

        $this->assertStringEndsWith('.png', $this->photoPath());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function notImages(): array
    {
        return [
            'texto con extensión .jpg' => ['texto', 'foto.jpg'],
            'svg' => ['svg.svg', 'foto.svg'],
            'svg con extensión .png' => ['svg.svg', 'foto.png'],
            // image acepta un GIF; mimetypes, no.
            'gif' => ['foto.gif', 'foto.gif'],
        ];
    }

    #[DataProvider('notImages')]
    public function test_a_file_that_is_not_a_jpg_png_or_webp_is_rejected(string $fixture, string $name): void
    {
        $this->actAs('recepcionista');
        $file = $fixture === 'texto' ? $this->fileWithContent('Esto es texto, no una imagen.', $name) : $this->file($fixture, $name);

        $response = $this->upload($file);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame('La foto debe ser una imagen JPG, PNG o WEBP.', $response->json('errors.photo.0'));
        $this->assertNull($this->photoPath());
        $this->assertSame([], Storage::disk(PatientPhotoService::DISK)->allFiles());
    }

    public function test_the_photo_is_required(): void
    {
        $this->actAs('recepcionista');

        $response = $this->postJson($this->url(), []);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.photo.0', 'La foto es obligatoria.');
    }

    public function test_a_photo_of_5_mb_is_accepted_and_a_larger_one_answers_413(): void
    {
        $this->actAs('recepcionista');
        $png = file_get_contents(self::FIXTURES . 'foto.png');
        $limit = UploadPatientPhotoRequest::MAX_KILOBYTES * 1024;

        // Un PNG con relleno al final sigue siendo un PNG para finfo.
        $this->upload($this->fileWithContent($png . str_repeat("\0", $limit - strlen($png)), 'grande.png'))->assertOk();

        $response = $this->upload($this->fileWithContent($png . str_repeat("\0", $limit - strlen($png) + 1), 'enorme.png'));

        $this->assertErrorCode($response, 413, 'PAYLOAD_TOO_LARGE');
        $response->assertJsonPath('errors.photo.0', 'La foto no debe pesar más de 5 MB.');
        $this->assertCount(1, Storage::disk(PatientPhotoService::DISK)->allFiles());
    }

    /**
     * PHP corta el archivo antes de Laravel: upload_max_filesize (el archivo llega
     * con error) o post_max_size (el pedido entero; bootstrap/app.php).
     */
    public function test_a_file_cut_by_php_answers_413(): void
    {
        $this->actAs('recepcionista');
        $tmp = tempnam(sys_get_temp_dir(), 'foto');
        file_put_contents($tmp, '');

        $response = $this->upload(new UploadedFile($tmp, 'enorme.jpg', null, UPLOAD_ERR_INI_SIZE, true));
        $this->assertErrorCode($response, 413, 'PAYLOAD_TOO_LARGE');

        $response = $this->call('POST', $this->url(), [], [], [], [
            'CONTENT_LENGTH' => (string) (1024 ** 4),
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertErrorCode($response, 413, 'PAYLOAD_TOO_LARGE');
    }

    public function test_replacing_the_photo_deletes_the_previous_file(): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->photoPath();

        $this->upload($this->file('foto.webp'))->assertOk();

        $current = $this->photoPath();
        $this->assertNotSame($previous, $current);
        Storage::disk(PatientPhotoService::DISK)->assertMissing($previous);
        Storage::disk(PatientPhotoService::DISK)->assertExists($current);
        $this->assertSame([$current], Storage::disk(PatientPhotoService::DISK)->allFiles());
    }

    /**
     * Si la transacción falla, se borra el archivo nuevo y queda el anterior.
     */
    public function test_a_failed_save_deletes_the_new_file_and_keeps_the_previous_one(): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->photoPath();

        Patient::saving(fn () => throw new RuntimeException('falla al guardar'));
        $this->upload($this->file('foto.png'))->assertStatus(500);

        $this->assertSame($previous, $this->photoPath());
        $this->assertSame([$previous], Storage::disk(PatientPhotoService::DISK)->allFiles());
    }

    /**
     * El anterior se borra después del commit: si la transacción de afuera se
     * revierte, la foto anterior sigue siendo la del paciente y su archivo queda.
     */
    public function test_the_previous_file_is_deleted_only_after_the_commit(): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->photoPath();

        DB::beginTransaction();
        $this->upload($this->file('foto.png'))->assertOk();
        $this->deleteJson($this->url())->assertOk();
        Storage::disk(PatientPhotoService::DISK)->assertExists($previous);
        DB::rollBack();

        $this->assertSame($previous, $this->photoPath());
        Storage::disk(PatientPhotoService::DISK)->assertExists($previous);
    }

    #[DataProvider('images')]
    public function test_the_download_is_the_file_with_nosniff_and_no_store(string $fixture, string $extension, string $type): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file($fixture))->assertOk();

        $response = $this->get($this->url(), ['Accept' => 'application/json'])->assertOk();

        $response->assertHeader('Content-Type', $type);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->assertHeader('Content-Disposition', "inline; filename={$this->patient->code}.{$extension}");
        $this->assertSame(file_get_contents(self::FIXTURES . $fixture), $response->streamedContent());
    }

    public function test_without_photo_seeing_or_deleting_it_answers_404(): void
    {
        $this->actAs('recepcionista');

        $this->assertErrorCode($this->getJson($this->url()), 404, 'PATIENT_PHOTO_NOT_FOUND');
        $this->assertErrorCode($this->deleteJson($this->url()), 404, 'PATIENT_PHOTO_NOT_FOUND');
        $this->getJson("/api/patients/{$this->patient->id}")->assertJsonPath('data.has_photo', false);
    }

    public function test_deleting_the_photo_removes_the_file_and_the_path(): void
    {
        $this->actAs('veterinario');
        $this->upload($this->file('foto.png'))->assertOk();
        $path = $this->photoPath();

        $this->deleteJson($this->url())->assertOk()
            ->assertJsonPath('data.has_photo', false)
            ->assertJsonPath('data.id', $this->patient->id);

        $this->assertNull($this->photoPath());
        Storage::disk(PatientPhotoService::DISK)->assertMissing($path);
    }

    public function test_a_patient_of_another_clinic_answers_404(): void
    {
        $other = $this->createClinic();
        $foreign = $this->patientOf($this->clientIn($other));
        Storage::disk(PatientPhotoService::DISK)->put('clinics/ajena.png', 'png');
        DB::table('patients')->where('id', $foreign->id)->update(['photo_path' => 'clinics/ajena.png']);
        $this->actAs('recepcionista');
        $url = "/api/patients/{$foreign->id}/photo";

        $this->assertErrorCode($this->upload($this->file('foto.png'), $url), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->getJson($url), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->deleteJson($url), 404, 'RESOURCE_NOT_FOUND');

        $this->assertSame('clinics/ajena.png', DB::table('patients')->where('id', $foreign->id)->value('photo_path'));
        Storage::disk(PatientPhotoService::DISK)->assertExists('clinics/ajena.png');
    }

    public function test_a_lost_file_answers_404_and_warns_in_the_log(): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file('foto.png'))->assertOk();
        Storage::disk(PatientPhotoService::DISK)->delete($this->photoPath());
        Log::spy();

        $this->assertErrorCode($this->getJson($this->url()), 404, 'PATIENT_PHOTO_NOT_FOUND');

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $context['patient_id'] === $this->patient->id
        );
    }

    /**
     * Pregunta 12 del diseño: la foto no es un dato clínico.
     */
    #[DataProvider('roles')]
    public function test_a_deceased_patient_admits_uploading_and_deleting_the_photo(string $role): void
    {
        DB::table('patients')->where('id', $this->patient->id)->update(['deceased_at' => now()]);
        $this->actAs($role);

        $this->upload($this->file('foto.png'))->assertOk()->assertJsonPath('data.has_photo', true);
        $this->upload($this->file('foto.jpg'))->assertOk();
        $this->deleteJson($this->url())->assertOk()->assertJsonPath('data.has_photo', false);
    }

    public function test_the_change_is_audited(): void
    {
        $this->actAs('recepcionista');

        $this->upload($this->file('foto.png'))->assertOk();

        $log = DB::table('audit_logs')->where('auditable_type', 'patient')->where('auditable_id', $this->patient->id)
            ->where('event', AuditEvent::UPDATED->value)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('photo_path', $log->new_values);
    }

    /**
     * La baja del paciente es SoftDeletes: el archivo queda con el registro.
     */
    public function test_deleting_the_patient_keeps_the_file(): void
    {
        $this->actAs('recepcionista');
        $this->upload($this->file('foto.png'))->assertOk();
        $path = $this->photoPath();

        CurrentClinic::run($this->clinic, fn () => $this->patient->fresh()->delete());

        Storage::disk(PatientPhotoService::DISK)->assertExists($path);
    }

    /**
     * La subida y el borrado leen photo_path con la fila del paciente bloqueada:
     * dos subidas simultáneas no dejan un archivo huérfano. "for update" solo en MySQL.
     */
    public function test_upload_and_delete_lock_the_patient_before_reading_the_previous_photo(): void
    {
        $this->actAs('recepcionista');
        $requests = [
            fn () => $this->upload($this->file('foto.png'))->assertOk(),
            fn () => $this->deleteJson($this->url())->assertOk(),
        ];

        foreach ($requests as $request) {
            $base = DB::transactionLevel();
            $queries = [];
            DB::listen(function ($query) use (&$queries, $base) {
                if ($query->connection->transactionLevel() > $base) {
                    $queries[] = strtolower($query->sql);
                }
            });

            $request();

            $lock = array_key_first(array_filter($queries, fn (string $sql) => preg_match('/^select [`"]photo_path[`"] from [`"]patients[`"]/', $sql) === 1
                && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'))));
            $write = array_key_first(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'update ') && str_contains($sql, 'patients')));

            $this->assertNotNull($lock, "No se bloqueó el paciente:\n" . implode("\n", $queries));
            $this->assertNotNull($write, "No se guardó photo_path:\n" . implode("\n", $queries));
            $this->assertLessThan($write, $lock);
        }
    }

    private function actAs(string $role): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, $role));
    }

    private function url(): string
    {
        return "/api/patients/{$this->patient->id}/photo";
    }

    private function upload(UploadedFile $file, ?string $url = null): TestResponse
    {
        return $this->post($url ?? $this->url(), ['photo' => $file], ['Accept' => 'application/json']);
    }

    private function photoPath(): ?string
    {
        return DB::table('patients')->where('id', $this->patient->id)->value('photo_path');
    }

    /**
     * Copia de un archivo de tests/Fixtures/photos, como lo sube un navegador.
     */
    private function file(string $fixture, ?string $name = null): UploadedFile
    {
        return $this->fileWithContent(file_get_contents(self::FIXTURES . $fixture), $name ?? $fixture);
    }

    private function fileWithContent(string $content, string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'foto');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }
}
