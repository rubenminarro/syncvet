<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Services\ConsultationAttachmentService;
use App\Support\CurrentClinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Los adjuntos de la consulta (FASE 4, entrega 4.5; pregunta 17): imágenes JPEG, PNG y WebP y PDF, de
 * hasta 10 MB y 20 por consulta, en el disco privado consultation_attachments.
 *
 * Los archivos son reales (Illuminate\Http\UploadedFile sobre un archivo temporal): el UploadedFile::fake()
 * de Laravel informa el tipo por la extensión del nombre, y acá se prueba que el tipo sale del contenido.
 */
class ConsultationAttachmentTest extends TestCase
{
    use InteractsWithConsultations;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /** @var list<string> */
    private array $temporaryFiles = [];

    private int $consultation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ConsultationAttachmentService::DISK);
        $this->setUpClinic();
        $this->actAsVet();
        $this->consultation = $this->opened(['appointment_id' => $this->checkedIn()]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public static function allowedTypes(): array
    {
        return [
            'JPEG' => ['jpeg', 'radiografia.jpg', 'image/jpeg', 'jpg'],
            'PNG' => ['png', 'ecografia.png', 'image/png', 'png'],
            'WebP' => ['webp', 'foto.webp', 'image/webp', 'webp'],
            'PDF' => ['pdf', 'laboratorio.pdf', 'application/pdf', 'pdf'],
        ];
    }

    /**
     * Cada tipo permitido se guarda en la carpeta de la consulta con un nombre generado ({uuid}.{ext} del
     * tipo detectado), el nombre original para mostrar, el tamaño y quién lo subió; responde 201 con el
     * detalle, que lista el adjunto sin la ruta.
     */
    #[DataProvider('allowedTypes')]
    public function test_each_allowed_type_is_stored_privately_with_a_generated_name(string $kind, string $name, string $mime, string $extension): void
    {
        $user = auth()->user();
        $response = $this->upload($this->file($this->contents($kind), $name))->assertCreated();

        $row = DB::table('consultation_attachments')->sole();
        $folder = "clinics/{$this->clinic->id}/consultations/{$this->consultation}/";
        $this->assertMatchesRegularExpression('#^' . preg_quote($folder, '#') . '[0-9a-f-]{36}\.' . $extension . '$#', $row->path);
        Storage::disk(ConsultationAttachmentService::DISK)->assertExists($row->path);
        $this->assertSame([$name, $mime, $user->id], [$row->original_name, $row->mime_type, $row->uploaded_by]);
        $this->assertSame(strlen($this->contents($kind)), (int) $row->size_bytes);

        $response->assertJsonPath('data.attachments.0.id', $row->id)
            ->assertJsonPath('data.attachments.0.original_name', $name)
            ->assertJsonPath('data.attachments.0.mime_type', $mime)
            ->assertJsonPath('data.attachments.0.uploaded_by.id', $user->id);
        $this->assertStringNotContainsString('clinics/', $response->getContent());
        $this->assertArrayNotHasKey('path', $response->json('data.attachments.0'));
    }

    public static function lyingFiles(): array
    {
        return [
            // El contenido manda: un PNG con nombre y Content-Type de PDF se guarda como PNG.
            'un PNG llamado .pdf' => ['png', 'informe.pdf', 'application/pdf', 201, 'image/png', 'png'],
            'un PDF llamado .png' => ['pdf', 'placa.png', 'image/png', 201, 'application/pdf', 'pdf'],
            // Y lo que no es ni imagen ni PDF se rechaza aunque el nombre y el Content-Type digan PDF o PNG.
            'un HTML llamado .pdf' => ['html', 'estudio.pdf', 'application/pdf', 422, null, null],
            'un texto llamado .png' => ['text', 'placa.png', 'image/png', 422, null, null],
            'un SVG llamado .png' => ['svg', 'placa.png', 'image/png', 422, null, null],
        ];
    }

    /**
     * El tipo se valida por el contenido (finfo), nunca por la extensión ni por el Content-Type del pedido.
     */
    #[DataProvider('lyingFiles')]
    public function test_the_type_comes_from_the_content_not_from_the_name(string $kind, string $name, string $clientType, int $status, ?string $mime, ?string $extension): void
    {
        $response = $this->upload($this->file($this->contents($kind), $name, $clientType));

        if ($status === 422) {
            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.file.0', 'El archivo debe ser una imagen JPEG, PNG o WebP, o un PDF.');
            $this->assertSame(0, DB::table('consultation_attachments')->count());
            $this->assertSame([], Storage::disk(ConsultationAttachmentService::DISK)->allFiles());

            return;
        }

        $response->assertCreated();
        $row = DB::table('consultation_attachments')->sole();
        $this->assertSame($mime, $row->mime_type);
        $this->assertStringEndsWith(".{$extension}", $row->path);
        // El nombre para mostrar queda como vino; la descarga lleva la extensión del tipo detectado.
        $this->assertSame($name, $row->original_name);
        $this->get($this->downloadUri($row->id))->assertOk()
            ->assertHeader('Content-Type', $mime)
            ->assertDownload(pathinfo($name, PATHINFO_FILENAME) . ".{$extension}");
    }

    public static function sizes(): array
    {
        return [
            'justo 10 MB' => [10 * 1024 * 1024, 201],
            '10 MB y un byte' => [10 * 1024 * 1024 + 1, 413],
        ];
    }

    #[DataProvider('sizes')]
    public function test_up_to_10_mb_each(int $bytes, int $status): void
    {
        $response = $this->upload($this->file(str_pad(self::PDF, $bytes, ' '), 'grande.pdf'));

        if ($status === 413) {
            $this->assertErrorCode($response, 413, 'PAYLOAD_TOO_LARGE');
            $response->assertJsonPath('errors.file.0', 'El archivo no debe pesar más de 10 MB.');
            $this->assertSame(0, DB::table('consultation_attachments')->count());

            return;
        }

        $response->assertCreated();
        $this->assertSame($bytes, (int) DB::table('consultation_attachments')->value('size_bytes'));
    }

    /**
     * Lo que PHP corta antes de Laravel (upload_max_filesize): también 413, no 422.
     */
    public function test_a_file_cut_by_php_is_413(): void
    {
        $path = $this->temporary(self::PDF);
        $file = new UploadedFile($path, 'grande.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $this->assertErrorCode($this->upload($file), 413, 'PAYLOAD_TOO_LARGE');
    }

    public function test_the_file_is_required(): void
    {
        $response = $this->post("/api/consultations/{$this->consultation}/attachments", [], ['Accept' => 'application/json']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.file.0', 'Elegí el archivo que querés adjuntar.');
    }

    public static function counts(): array
    {
        return [
            'el 20 entra' => [19, 201],
            'el 21 no' => [20, 422],
        ];
    }

    /**
     * Hasta 20 por consulta, contados con la consulta bloqueada; el 21 responde 422 en file y su archivo,
     * ya escrito, se borra.
     */
    #[DataProvider('counts')]
    public function test_up_to_20_per_consultation(int $existing, int $status): void
    {
        $this->insertAttachments($existing);

        $response = $this->upload($this->file(self::PDF, 'otro.pdf'));

        if ($status === 422) {
            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.file.0', ConsultationAttachmentService::TOO_MANY);
            $this->assertSame([], Storage::disk(ConsultationAttachmentService::DISK)->allFiles());
        } else {
            $response->assertCreated()->assertJsonCount(20, 'data.attachments');
        }

        $this->assertSame($existing + ($status === 201 ? 1 : 0), DB::table('consultation_attachments')->count());
    }

    /**
     * Otro veterinario no sube ni quita adjuntos (403 CONSULTATION_NOT_YOURS); el archivo escrito se borra.
     */
    public function test_another_veterinarian_cannot_upload_or_remove(): void
    {
        $id = $this->uploaded('pdf', 'laboratorio.pdf');
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertErrorCode($this->upload($this->file(self::PDF, 'otro.pdf')), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertErrorCode($this->deleteJson("/api/consultation-attachments/{$id}"), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertSame(1, DB::table('consultation_attachments')->count());
        $this->assertCount(1, Storage::disk(ConsultationAttachmentService::DISK)->allFiles());
    }

    /**
     * La descarga: con consultation-attachments.index (el veterinario, también de otra ficha, y el
     * administrador), el archivo con el tipo detectado, el nombre saneado, nosniff, una CSP que no deja
     * ejecutar nada y sin caché; sin el permiso (la recepción), 403 y sin el archivo.
     */
    public function test_download_with_and_without_permission(): void
    {
        $id = $this->uploaded('png', 'ecografía abdominal.png');
        $expected = ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        foreach (['vet' => fn () => $this->actAsVet(), 'otro vet' => fn () => $this->actAsVet($this->veterinarianIn($this->branch)), 'admin' => fn () => $this->actAsAdmin()] as $who => $act) {
            $act();
            $response = $this->get($this->downloadUri($id))->assertOk();

            foreach ($expected as $header => $value) {
                $this->assertSame($value, $response->headers->get($header), "{$who}: {$header}");
            }

            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            // El nombre ASCII de respaldo y el UTF-8 (RFC 6266).
            $response->assertDownload('ecografia abdominal.png');
            $this->assertStringContainsString("filename*=utf-8''ecograf%C3%ADa%20abdominal.png", $response->headers->get('Content-Disposition'));
            $this->assertSame($this->contents('png'), $response->streamedContent(), $who);
        }

        $this->actAsReceptionist();
        $response = $this->get($this->downloadUri($id), ['Accept' => 'application/json']);
        $this->assertErrorCode($response, 403, 'FORBIDDEN');
        $this->assertStringNotContainsString('PNG', $response->getContent());
    }

    /**
     * Los adjuntos salen en el detalle solo con consultations.show-clinical: la recepción no los ve.
     */
    public function test_the_detail_lists_attachments_only_with_the_clinical_permission(): void
    {
        $this->uploaded('pdf', 'laboratorio.pdf');

        $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->assertJsonCount(1, 'data.attachments');
        $this->actAsReceptionist();
        $this->assertArrayNotHasKey('attachments', $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->json('data'));
    }

    /**
     * Un adjunto de otra consulta de la misma clínica no se descarga por esta (scopeBindings): 404.
     */
    public function test_an_attachment_of_another_consultation_is_not_found_through_this_one(): void
    {
        $id = $this->uploaded('pdf', 'laboratorio.pdf');
        $other = $this->opened(['patient_id' => $this->patientOf($this->clientIn($this->clinic))->id]);

        $this->assertErrorCode($this->getJson("/api/consultations/{$other}/attachments/{$id}"), 404, 'RESOURCE_NOT_FOUND');
    }

    /**
     * Un adjunto sin archivo en el disco, o con una ruta fuera de la carpeta de su consulta (no la escribe
     * la API), responde 404, sin la ruta.
     */
    public function test_a_missing_or_foreign_file_is_not_found(): void
    {
        $id = $this->uploaded('pdf', 'laboratorio.pdf');
        $path = DB::table('consultation_attachments')->where('id', $id)->value('path');
        Storage::disk(ConsultationAttachmentService::DISK)->delete($path);

        $response = $this->getJson($this->downloadUri($id));
        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');
        $this->assertStringNotContainsString('clinics/', $response->getContent());

        $foreign = "clinics/{$this->clinic->id}/patients/1/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.pdf";
        Storage::disk(ConsultationAttachmentService::DISK)->put($foreign, self::PDF);
        DB::table('consultation_attachments')->where('id', $id)->update(['path' => $foreign]);
        $this->assertErrorCode($this->getJson($this->downloadUri($id)), 404, 'RESOURCE_NOT_FOUND');
    }

    /**
     * Quitar (abierta): borra la fila y el archivo y queda auditado, con la ruta como "(archivo)".
     */
    public function test_remove_deletes_the_row_and_the_file_and_audits_without_the_path(): void
    {
        $id = $this->uploaded('pdf', 'laboratorio.pdf');
        $path = DB::table('consultation_attachments')->where('id', $id)->value('path');

        $this->deleteJson("/api/consultation-attachments/{$id}")->assertOk()->assertJsonCount(0, 'data.attachments');

        $this->assertSame(0, DB::table('consultation_attachments')->count());
        Storage::disk(ConsultationAttachmentService::DISK)->assertMissing($path);
        $logs = DB::table('audit_logs')->where('auditable_type', 'consultation_attachment')->where('auditable_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame('(archivo)', json_decode($logs[0]->new_values, true)['path']);
        $this->assertSame('(archivo)', json_decode($logs[1]->old_values, true)['path']);
        $this->assertStringNotContainsString('clinics/', $logs->pluck('old_values')->implode('') . $logs->pluck('new_values')->implode(''));
    }

    public static function names(): array
    {
        return [
            'con carpetas' => ['../../etc/passwd.pdf', 'passwd.pdf'],
            'con barras de Windows' => ['C:\\Users\\ana\\placa.png', 'placa.png'],
            'con caracteres de control y comillas' => ["inf\"orme\r\n<x>.pdf", 'informex.pdf'],
            'vacío' => ['...', 'adjunto'],
            'largo' => [str_repeat('a', 300) . '.pdf', str_repeat('a', 251) . '.pdf'],
        ];
    }

    #[DataProvider('names')]
    public function test_the_original_name_is_sanitized(string $name, string $expected): void
    {
        $this->assertSame($expected, ConsultationAttachmentService::sanitizeName($name));
    }

    /**
     * El disco es privado: fuera de public/, sin `serve` y sin URL pública.
     */
    public function test_the_disk_is_private(): void
    {
        $disk = config('filesystems.disks.' . ConsultationAttachmentService::DISK);

        $this->assertSame(storage_path('app/private/consultation-attachments'), $disk['root']);
        $this->assertFalse($disk['serve']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertArrayNotHasKey('visibility', $disk);
    }

    /**
     * files:prune-orphans cubre el disco: borra un archivo viejo sin fila en la carpeta de adjuntos de la
     * clínica y conserva el de un adjunto y uno reciente.
     */
    public function test_prune_orphans_covers_the_attachments_disk(): void
    {
        $id = $this->uploaded('pdf', 'laboratorio.pdf');
        $kept = DB::table('consultation_attachments')->where('id', $id)->value('path');
        $folder = "clinics/{$this->clinic->id}/consultations/{$this->consultation}/";
        $orphan = $folder . 'aaaaaaaa-bbbb-4ccc-8ddd-000000000001.pdf';
        $recent = $folder . 'aaaaaaaa-bbbb-4ccc-8ddd-000000000002.pdf';
        $disk = Storage::disk(ConsultationAttachmentService::DISK);
        $disk->put($orphan, self::PDF);
        $disk->put($recent, self::PDF);
        $old = now()->subHours(25)->getTimestamp();
        touch($disk->path($orphan), $old);
        touch($disk->path($kept), $old);
        touch($disk->path($recent), now()->subHours(23)->getTimestamp());

        $this->artisan('files:prune-orphans')
            ->expectsOutput('Borrado: consultation_attachments/' . $orphan)
            ->expectsOutput('Archivos huérfanos borrados: 1 de 1.')
            ->assertExitCode(0);

        $disk->assertMissing($orphan);
        $disk->assertExists($kept);
        $disk->assertExists($recent);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post("/api/consultations/{$this->consultation}/attachments", ['file' => $file], ['Accept' => 'application/json']);
    }

    private function uploaded(string $kind, string $name): int
    {
        return $this->upload($this->file($this->contents($kind), $name))->assertCreated()->json('data.attachments.0.id');
    }

    private function downloadUri(int $id): string
    {
        return "/api/consultations/{$this->consultation}/attachments/{$id}";
    }

    /**
     * Un archivo subido real (test: true), con el nombre y el Content-Type que manda el cliente.
     */
    private function file(string $contents, string $name, ?string $clientType = null): UploadedFile
    {
        return new UploadedFile($this->temporary($contents), $name, $clientType ?? 'application/octet-stream', null, true);
    }

    private function temporary(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'adj');
        file_put_contents($path, $contents);

        return $this->temporaryFiles[] = $path;
    }

    private function contents(string $kind): string
    {
        static $cache = [];

        return $cache[$kind] ??= match ($kind) {
            'pdf' => self::PDF,
            'html' => "<!DOCTYPE html>\n<html><body><script>alert(1)</script></body></html>\n",
            'text' => "Resultado del laboratorio: normal.\n",
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>',
            default => $this->image($kind),
        };
    }

    private function image(string $kind): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        match ($kind) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
        };
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * $count adjuntos de la consulta, insertados directo (con un archivo cada uno no hace falta: el tope
     * cuenta filas).
     */
    private function insertAttachments(int $count): void
    {
        $userId = auth()->id();

        for ($i = 1; $i <= $count; $i++) {
            DB::table('consultation_attachments')->insert([
                'clinic_id' => $this->clinic->id, 'consultation_id' => $this->consultation,
                'path' => "clinics/{$this->clinic->id}/consultations/{$this->consultation}/" . sprintf('aaaaaaaa-bbbb-4ccc-8ddd-%012d', $i) . '.pdf',
                'original_name' => "estudio {$i}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 10,
                'uploaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
