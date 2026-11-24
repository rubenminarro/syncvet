<?php

namespace App\Services;

use App\Exceptions\ConsultationException;
use App\Models\Consultation;
use App\Models\ConsultationAttachment;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Los adjuntos de la consulta (FASE 4, entrega 4.5; pregunta 17): imágenes (JPEG, PNG, WebP) y PDF,
 * hasta 10 MB cada uno (StoreConsultationAttachmentRequest) y 20 por consulta.
 *
 * - Disco propio consultation_attachments (privado, fuera de public/ y sin `serve`, como
 *   patient_photos), en clinics/{clinic_id}/consultations/{consultation_id}/{uuid}.{ext}: el nombre es
 *   al azar y la extensión sale del tipo detectado en el contenido, nunca del archivo subido. El nombre
 *   original se guarda saneado (sanitizeName), solo para mostrar.
 * - Subir y quitar: solo con la consulta abierta, por su veterinario o el administrador
 *   (ConsultationService::lockEditable: 403 CONSULTATION_NOT_YOURS; 422 CONSULTATION_NOT_EDITABLE). El
 *   tope de 20 se cuenta con la consulta bloqueada (dos subidas simultáneas no pasan de 20): el 21,
 *   422 en file.
 * - El archivo se escribe antes de la transacción; si la transacción falla (la consulta no es editable,
 *   el tope, un deadlock agotado), se borra y la excepción sigue. Quitar borra la fila y, después del
 *   commit, el archivo; un borrado que falla queda en el log y lo levanta files:prune-orphans.
 * - Descargar: siempre por el endpoint con permiso (consultation-attachments.index), con el tipo
 *   detectado, el nombre saneado (con la extensión del tipo detectado), nosniff, una CSP que no deja
 *   ejecutar nada y sin caché. Una ruta fuera de la carpeta de la consulta o sin archivo, 404 y un
 *   aviso en el log. Ninguna respuesta lleva la ruta.
 * - Auditoría: el alta y la baja (Auditable), con la ruta como "(archivo)".
 *
 * Concurrencia (§3: consulta → adjunto): la consulta con lockForUpdate (lockEditable) y después el
 * adjunto. Los reintentos de AppointmentService::withRetries.
 */
class ConsultationAttachmentService
{
    public const DISK = 'consultation_attachments';

    public const MAX_PER_CONSULTATION = 20;

    public const TOO_MANY = 'La consulta ya tiene 20 adjuntos: quitá alguno para sumar otro.';

    public const NOT_FOUND = 'El archivo del adjunto no está disponible.';

    /**
     * Tipo detectado → extensión del archivo guardado.
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * El nombre de un archivo guardado: {uuid}.{ext}.
     */
    public const FILE_NAME = '/^[0-9a-f-]{36}\.(jpg|png|webp|pdf)$/';

    /**
     * El nombre para mostrar cuando el original queda vacío al sanearlo.
     */
    public const DEFAULT_NAME = 'adjunto';

    public function __construct(
        protected AppointmentService $appointments,
        protected ConsultationService $consultations
    ) {
    }

    /**
     * $mimeType: el tipo detectado en el contenido (StoreConsultationAttachmentRequest::detectedMimeType).
     */
    public function upload(Consultation $consultation, UploadedFile $file, string $mimeType, User $user): Consultation
    {
        $folder = self::folder($consultation);
        $path = $this->disk()->putFileAs(rtrim($folder, '/'), $file, Str::uuid() . '.' . self::EXTENSIONS[$mimeType]);

        if ($path === false) {
            // Falla del disco, no de negocio: un 500 genérico, y se reporta.
            throw new RuntimeException('No se pudo guardar el adjunto de la consulta: ' . json_encode($this->context($consultation)));
        }

        try {
            return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $file, $mimeType, $path, $user) {
                $locked = $this->consultations->lockEditable($consultation, $user);

                if (ConsultationAttachment::query()->where('consultation_id', $locked->getKey())->count() >= self::MAX_PER_CONSULTATION) {
                    throw ValidationException::withMessages(['file' => self::TOO_MANY]);
                }

                ConsultationAttachment::create([
                    'consultation_id' => $locked->getKey(),
                    'path' => $path,
                    'original_name' => self::sanitizeName($file->getClientOriginalName()),
                    'mime_type' => $mimeType,
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $user->getKey(),
                ]);

                return $locked;
            }), ConsultationService::CONFLICT);
        } catch (Throwable $e) {
            $this->deleteOrWarn($path, $this->context($consultation), 'la subida falló');

            throw $e;
        }
    }

    public function remove(ConsultationAttachment $attachment, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($attachment, $user) {
            $consultation = Consultation::query()->whereKey($attachment->consultation_id)->firstOrFail();
            $locked = $this->consultations->lockEditable($consultation, $user);
            $lockedAttachment = ConsultationAttachment::query()->whereKey($attachment->getKey())->lockForUpdate()->firstOrFail();
            $path = $lockedAttachment->path;
            $lockedAttachment->delete();

            // Solo un archivo de la carpeta de la consulta: una ruta ajena (no la escribe la API) no se toca.
            if ($this->owns($locked, $path)) {
                DB::afterCommit(fn () => $this->deleteOrWarn($path, $this->context($locked), 'después del commit'));
            }

            return $locked;
        }), ConsultationService::CONFLICT);
    }

    /**
     * El archivo, con el tipo detectado al subirlo, el nombre saneado, nosniff, una CSP que no deja
     * ejecutar nada y sin caché.
     */
    public function download(Consultation $consultation, ConsultationAttachment $attachment): StreamedResponse
    {
        $path = $attachment->path;

        if (! $this->owns($consultation, $path)) {
            throw $this->notFound();
        }

        if (! $this->disk()->exists($path)) {
            Log::warning('Adjunto de consulta sin archivo en el disco.', [...$this->context($consultation), 'attachment_id' => $attachment->id]);

            throw $this->notFound();
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $type = array_search($extension, self::EXTENSIONS, true) ?: 'application/octet-stream';

        return $this->disk()->response($path, self::downloadName((string) $attachment->original_name, $extension), [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /**
     * clinics/{clinic_id}/consultations/{consultation_id}/, con la barra final.
     */
    public static function folder(Consultation $consultation): string
    {
        return "clinics/{$consultation->clinic_id}/consultations/{$consultation->id}/";
    }

    /**
     * El nombre original, solo para mostrar: sin carpetas, sin caracteres de control (C0 y C1) ni marcas
     * de dirección del texto, ni los que un sistema de archivos o un encabezado no aceptan
     * (\ / : * ? " < > |), con los espacios juntos y
     * hasta 255 caracteres (conserva la extensión); vacío, "adjunto".
     */
    public static function sanitizeName(?string $name): string
    {
        $name = Str::of((string) $name)
            ->replace('\\', '/')
            ->afterLast('/')
            // Revisión de la 4.5: también los controles C1 (\p{Cc}) y las marcas de dirección (LRM, RLM,
            // LRE…RLO, LRI…PDI), que harían ver "informe‮fdp.exe" como "informeexe.pdf".
            ->replaceMatches('/[\p{Cc}:*?"<>|\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '')
            ->squish()
            ->trim(' .')
            ->value();

        // Un nombre en UTF-8 inválido no se muestra.
        if ($name === '' || ! mb_check_encoding($name, 'UTF-8')) {
            return self::DEFAULT_NAME;
        }

        if (mb_strlen($name) <= 255) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' && mb_strlen($extension) <= 10 ? '.' . $extension : '';

        return mb_substr($name, 0, 255 - mb_strlen($suffix)) . $suffix;
    }

    /**
     * El nombre de la descarga: el nombre saneado con la extensión del tipo detectado (un PNG subido como
     * "estudio.pdf" se descarga como "estudio.png").
     */
    public static function downloadName(string $originalName, string $extension): string
    {
        $base = pathinfo(self::sanitizeName($originalName), PATHINFO_FILENAME);

        return ($base !== '' ? $base : self::DEFAULT_NAME) . '.' . $extension;
    }

    /**
     * true si la ruta es un archivo de la carpeta de la consulta ({uuid}.{ext}, sin subcarpetas ni "..");
     * si no, lo registra.
     */
    private function owns(Consultation $consultation, ?string $path): bool
    {
        $folder = self::folder($consultation);

        if ($path !== null && str_starts_with($path, $folder) && preg_match(self::FILE_NAME, substr($path, strlen($folder))) === 1) {
            return true;
        }

        Log::warning('Adjunto de consulta con una ruta fuera de su carpeta.', $this->context($consultation));

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function deleteOrWarn(string $path, array $context, string $moment): void
    {
        if (! $this->disk()->delete($path)) {
            Log::warning("No se pudo borrar un adjunto de consulta ({$moment}).", [...$context, 'path' => $path]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Consultation $consultation): array
    {
        return [
            'clinic_id' => $consultation->clinic_id,
            'consultation_id' => $consultation->id,
        ];
    }

    private function notFound(): ConsultationException
    {
        return new ConsultationException(self::NOT_FOUND, 404, 'RESOURCE_NOT_FOUND');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
