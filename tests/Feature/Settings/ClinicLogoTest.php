<?php

namespace Tests\Feature\Settings;

use App\Http\Requests\UploadClinicLogoRequest;
use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ClinicLogoService;
use App\Support\CurrentClinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Logo de la clínica (FASE 2, entrega 2.5; P12 y pregunta 25): system-settings/logo
 * para subir y borrar (el administrador) y ver (los tres roles). JPG, PNG o WEBP de
 * hasta 2 MB por el contenido, vuelto a codificar sin EXIF; más grande, 413. Disco
 * privado con nombre al azar; las reglas de la foto del paciente (PrivateImageStore).
 * La ruta va en logo_path y nunca sale: has_logo en la configuración y en `me`.
 *
 * Los archivos son reales (tests/Fixtures/photos), copiados a un temporal: el tipo se
 * detecta en sus bytes.
 */
class ClinicLogoTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithAuth;

    private const FIXTURES = __DIR__ . '/../../Fixtures/photos/';

    private const URL = '/api/system-settings/logo';

    private Clinic $clinic;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ClinicLogoService::DISK);
        $this->clinic = $this->createClinic();
        $this->admin = $this->userWithRole($this->clinic, 'administrador');
        Sanctum::actingAs($this->admin);
    }

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
        $response = $this->upload($this->file($fixture, 'Logo Original.' . $extension))->assertOk();

        $response->assertJsonPath('message', 'Logo de la clínica guardado correctamente.')
            ->assertJsonPath('data.has_logo', true)
            ->assertJsonMissingPath('data.settings.logo_path');
        $path = $this->logoPath();
        $this->assertMatchesRegularExpression("#^clinics/{$this->clinic->id}/logo/[0-9a-f-]{36}\.{$extension}$#", $path);
        $this->assertStringNotContainsString('Original', $path);
        $this->assertStringNotContainsString($path, $response->getContent());
        // Vuelto a codificar: el mismo formato y el mismo tamaño en píxeles.
        $saved = getimagesizefromstring(Storage::disk(ClinicLogoService::DISK)->get($path));
        $original = getimagesize(self::FIXTURES . $fixture);
        $this->assertSame([$original[0], $original[1], $type], [$saved[0], $saved[1], $saved['mime']]);
    }

    public function test_logos_go_to_their_own_private_disk(): void
    {
        $this->assertSame('clinic_logos', ClinicLogoService::DISK);
        $this->assertSame(storage_path('app/private/clinic-logos'), config('filesystems.disks.clinic_logos.root'));
        $this->assertFalse(config('filesystems.disks.clinic_logos.serve'));
    }

    public function test_the_logo_is_saved_without_exif(): void
    {
        $this->upload($this->file('foto-gps.jpg'))->assertOk();

        $saved = Storage::disk(ClinicLogoService::DISK)->get($this->logoPath());
        $this->assertStringNotContainsString("Exif\0\0", $saved);
        $tmp = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($tmp, $saved);
        $this->assertArrayNotHasKey('GPSLatitude', @exif_read_data($tmp) ?: []);
    }

    public static function notImages(): array
    {
        return [
            'texto con extensión .png' => ['texto', 'logo.png'],
            'svg' => ['svg.svg', 'logo.svg'],
            'svg con extensión .png' => ['svg.svg', 'logo.png'],
            'gif' => ['foto.gif', 'logo.gif'],
            'jpg que GD no decodifica' => ['roto', 'logo.jpg'],
        ];
    }

    #[DataProvider('notImages')]
    public function test_a_file_that_is_not_a_jpg_png_or_webp_is_rejected(string $fixture, string $name): void
    {
        $file = match ($fixture) {
            'texto' => $this->fileWithContent('Esto es texto, no una imagen.', $name),
            'roto' => $this->fileWithContent("\xFF\xD8\xFF\xE0<html><body>hola</body></html>", $name),
            default => $this->file($fixture, $name),
        };

        $response = $this->upload($file);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(UploadClinicLogoRequest::NOT_AN_IMAGE, $response->json('errors.logo.0'));
        $this->assertNull($this->logoPath());
        $this->assertSame([], Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    public function test_the_logo_is_required_and_more_than_25_megapixels_is_rejected(): void
    {
        $this->assertErrorCode($this->postJson(self::URL, []), 422, 'VALIDATION_ERROR');
        $this->postJson(self::URL, [])->assertJsonPath('errors.logo.0', 'El logo es obligatorio.');

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 6000, 5000, 8, 2, 0, 0, 0)) . $chunk('IEND', '');

        $response = $this->upload($this->fileWithContent($png, 'enorme.png'));

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(UploadClinicLogoRequest::TOO_MANY_PIXELS, $response->json('errors.logo.0'));
        $this->assertSame([], Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    public function test_a_logo_of_2_mb_is_accepted_and_a_larger_one_answers_413(): void
    {
        $png = file_get_contents(self::FIXTURES . 'foto.png');
        $limit = UploadClinicLogoRequest::MAX_KILOBYTES * 1024;

        // Un PNG con relleno al final sigue siendo un PNG para finfo.
        $this->upload($this->fileWithContent($png . str_repeat("\0", $limit - strlen($png)), 'grande.png'))->assertOk();

        $response = $this->upload($this->fileWithContent($png . str_repeat("\0", $limit - strlen($png) + 1), 'enorme.png'));

        $this->assertErrorCode($response, 413, 'PAYLOAD_TOO_LARGE');
        $response->assertJsonPath('errors.logo.0', 'El logo no debe pesar más de 2 MB.');
        $this->assertCount(1, Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->logoPath();

        $this->upload($this->file('foto.webp'))->assertOk();

        $current = $this->logoPath();
        $this->assertNotSame($previous, $current);
        $this->assertSame([$current], Storage::disk(ClinicLogoService::DISK)->allFiles());
        $this->assertSame(1, DB::table('system_settings')->where('clinic_id', $this->clinic->id)->where('key', 'logo_path')->count());
    }

    /**
     * El anterior se borra después del commit: si la transacción de afuera se revierte,
     * el logo anterior sigue siendo el de la clínica y su archivo queda.
     */
    public function test_the_previous_file_is_deleted_only_after_the_commit(): void
    {
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->logoPath();

        DB::beginTransaction();
        $this->upload($this->file('foto.png'))->assertOk();
        $this->deleteJson(self::URL)->assertOk();
        Storage::disk(ClinicLogoService::DISK)->assertExists($previous);
        DB::rollBack();

        $this->assertSame($previous, $this->logoPath());
        Storage::disk(ClinicLogoService::DISK)->assertExists($previous);
    }

    /**
     * Si la transacción falla, se borra el archivo nuevo y queda el anterior.
     */
    public function test_a_failed_save_deletes_the_new_file_and_keeps_the_previous_one(): void
    {
        $this->upload($this->file('foto.jpg'))->assertOk();
        $previous = $this->logoPath();

        SystemSetting::saving(fn () => throw new RuntimeException('falla al guardar'));
        $this->upload($this->file('foto.png'))->assertStatus(500);

        $this->assertSame($previous, $this->logoPath());
        $this->assertSame([$previous], Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    #[DataProvider('images')]
    public function test_the_download_is_the_file_with_nosniff_csp_and_no_store(string $fixture, string $extension, string $type): void
    {
        $this->upload($this->file($fixture))->assertOk();

        $response = $this->get(self::URL, ['Accept' => 'application/json'])->assertOk();

        $response->assertHeader('Content-Type', $type);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->assertHeader('Content-Disposition', "inline; filename=logo.{$extension}");
        $this->assertSame(Storage::disk(ClinicLogoService::DISK)->get($this->logoPath()), $response->streamedContent());
    }

    public static function readOnlyRoles(): array
    {
        return ['recepcionista' => ['recepcionista'], 'veterinario' => ['veterinario']];
    }

    /**
     * La recepción y el veterinario ven el logo y reciben 403 al subirlo o borrarlo.
     */
    #[DataProvider('readOnlyRoles')]
    public function test_the_other_roles_see_the_logo_but_do_not_change_it(string $role): void
    {
        $this->upload($this->file('foto.png'))->assertOk();
        $path = $this->logoPath();
        Sanctum::actingAs($this->userWithRole($this->clinic, $role));

        $this->get(self::URL, ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertErrorCode($this->upload($this->file('foto.jpg')), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->deleteJson(self::URL), 403, 'FORBIDDEN');

        $this->assertSame($path, $this->logoPath());
        $this->assertSame([$path], Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    public function test_without_logo_seeing_or_deleting_it_answers_404(): void
    {
        foreach ([$this->getJson(self::URL), $this->deleteJson(self::URL)] as $response) {
            $this->assertErrorCode($response, 404, 'CLINIC_LOGO_NOT_FOUND');
            $response->assertJsonPath('message', 'La clínica no tiene logo.');
        }
    }

    public function test_deleting_the_logo_removes_the_file_and_the_key(): void
    {
        $this->upload($this->file('foto.png'))->assertOk();
        $path = $this->logoPath();

        $this->deleteJson(self::URL)
            ->assertOk()
            ->assertJsonPath('message', 'Logo de la clínica eliminado correctamente.')
            ->assertJsonPath('data.has_logo', false);

        $this->assertNull($this->logoPath());
        Storage::disk(ClinicLogoService::DISK)->assertMissing($path);
        $this->assertErrorCode($this->getJson(self::URL), 404, 'CLINIC_LOGO_NOT_FOUND');
    }

    /**
     * logo_path solo se usa si es un archivo de la carpeta de la clínica: otro valor (de
     * otra clínica, con "..", la carpeta misma, otro nombre) → 404 con un aviso en el log,
     * y el archivo ajeno no se toca, tampoco al reemplazar el logo.
     */
    public function test_a_logo_path_outside_the_clinic_folder_is_not_used(): void
    {
        $other = $this->createClinic();
        $foreign = $this->logoIn($other->id);
        $folder = ClinicLogoService::folder($this->clinic->id);
        $paths = [
            $foreign,
            "{$folder}../../{$other->id}/logo/" . basename($foreign),
            rtrim($folder, '/'),
            "{$folder}logo.png",
        ];

        foreach ($paths as $path) {
            $this->setLogoPath($path);
            Log::spy();

            $this->assertErrorCode($this->get(self::URL, ['Accept' => 'application/json']), 404, 'CLINIC_LOGO_NOT_FOUND');
            $this->assertErrorCode($this->deleteJson(self::URL), 404, 'CLINIC_LOGO_NOT_FOUND');

            $this->assertSame($path, $this->logoPath());
            Log::shouldHaveReceived('warning')->withArgs(
                fn (string $message, array $context) => str_contains($message, 'fuera de su carpeta') && $context['logo_path'] === $path
            )->twice();
        }

        $this->setLogoPath($foreign);
        $this->upload($this->file('foto.png'))->assertOk();
        Storage::disk(ClinicLogoService::DISK)->assertExists($foreign);
    }

    public function test_a_lost_file_answers_404_and_warns_in_the_log(): void
    {
        $this->upload($this->file('foto.png'))->assertOk();
        Storage::disk(ClinicLogoService::DISK)->delete($this->logoPath());
        Log::spy();

        $this->assertErrorCode($this->getJson(self::URL), 404, 'CLINIC_LOGO_NOT_FOUND');

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'sin archivo') && $context['clinic_id'] === $this->clinic->id
        );
    }

    /**
     * has_logo en `me` (P12: el logo se muestra después del login) y en la
     * configuración; la ruta no sale en ninguna de las dos.
     */
    public function test_has_logo_is_in_me_and_in_the_settings(): void
    {
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.clinic.has_logo', false);
        $this->getJson('/api/system-settings')->assertOk()->assertJsonPath('data.has_logo', false);

        $this->upload($this->file('foto.png'))->assertOk();

        foreach (['/api/me' => 'data.clinic.has_logo', '/api/system-settings' => 'data.has_logo'] as $uri => $path) {
            $response = $this->getJson($uri)->assertOk()->assertJsonPath($path, true);
            $this->assertStringNotContainsString($this->logoPath(), $response->getContent());
        }

        // El logo de otra clínica no cuenta.
        Sanctum::actingAs($this->userWithRole($this->createClinic(), 'recepcionista'));
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.clinic.has_logo', false);
    }

    public function test_the_logo_of_another_clinic_is_not_seen(): void
    {
        $other = $this->createClinic();
        CurrentClinic::run($other, fn () => SystemSetting::create(['key' => 'logo_path', 'value' => $this->logoIn($other->id)]));

        $this->assertErrorCode($this->getJson(self::URL), 404, 'CLINIC_LOGO_NOT_FOUND');
        $this->assertErrorCode($this->deleteJson(self::URL), 404, 'CLINIC_LOGO_NOT_FOUND');
        $this->assertCount(1, Storage::disk(ClinicLogoService::DISK)->allFiles());
    }

    /**
     * La subida y el borrado leen logo_path con la fila de la clínica bloqueada (clínica
     * → configuración): dos subidas simultáneas no dejan un archivo huérfano.
     */
    public function test_upload_and_delete_lock_the_clinic_before_reading_the_logo_path(): void
    {
        $requests = [
            fn () => $this->upload($this->file('foto.png'))->assertOk(),
            fn () => $this->deleteJson(self::URL)->assertOk(),
        ];

        foreach ($requests as $request) {
            $queries = $this->queriesOf($request);

            $this->assertBefore($queries, $this->lockOf('clinics'), $this->readOf('system_settings'), 'Se leyó logo_path antes de bloquear la clínica.');
        }
    }

    public function test_the_change_is_audited(): void
    {
        $this->upload($this->file('foto.png'))->assertOk();
        $this->deleteJson(self::URL)->assertOk();

        $events = DB::table('audit_logs')->where('auditable_type', 'system_setting')->where('clinic_id', $this->clinic->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['created', 'deleted'], $events);
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->post(self::URL, ['logo' => $file], ['Accept' => 'application/json']);
    }

    /**
     * Un archivo en la carpeta del logo de una clínica, con el nombre como lo guarda el Service.
     */
    private function logoIn(int $clinicId): string
    {
        $path = ClinicLogoService::folder($clinicId) . Str::uuid() . '.png';
        Storage::disk(ClinicLogoService::DISK)->put($path, file_get_contents(self::FIXTURES . 'foto.png'));

        return $path;
    }

    private function logoPath(): ?string
    {
        return DB::table('system_settings')->where('clinic_id', $this->clinic->id)->where('key', 'logo_path')->value('value');
    }

    private function setLogoPath(string $path): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['clinic_id' => $this->clinic->id, 'key' => 'logo_path'],
            ['value' => $path, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function file(string $fixture, ?string $name = null): UploadedFile
    {
        return $this->fileWithContent(file_get_contents(self::FIXTURES . $fixture), $name ?? $fixture);
    }

    private function fileWithContent(string $content, string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }
}
