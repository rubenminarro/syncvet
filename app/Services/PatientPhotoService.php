<?php

namespace App\Services;

use App\Exceptions\PatientException;
use App\Models\Patient;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Foto del paciente (FASE 1, entrega 1.5). Disco propio `patient_photos` (privado,
 * fuera de public/ y sin `serve`; R-027), en
 * clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}: el nombre es al azar y la
 * extensión sale del tipo detectado en el contenido, nunca del archivo subido. Se
 * guarda la imagen vuelta a codificar por el Form Request (sin metadatos ni
 * contenido pegado; ImageReencoder), sin redimensionar. La foto no es un dato
 * clínico: un paciente fallecido también la cambia o la borra (pregunta 12).
 *
 * El cambio de photo_path va en una transacción con la fila del paciente bloqueada
 * (dos subidas simultáneas no dejan un archivo huérfano). El archivo anterior se
 * borra después del commit; si la transacción falla, se borra el nuevo. Un borrado
 * que falla queda en el log (deuda 14: no hay limpieza de huérfanos).
 *
 * photo_path solo se usa si está en la carpeta del paciente: otro valor (no lo
 * escribe la API; R-027) responde 404 al verla o borrarla, no se borra al
 * reemplazarla, y queda en el log.
 */
class PatientPhotoService
{
    public const DISK = 'patient_photos';

    public const NOT_FOUND = 'El paciente no tiene foto.';

    /**
     * Tipo detectado → extensión del archivo guardado.
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * $contents: la imagen ya codificada de nuevo; $mimeType: el tipo detectado.
     */
    public function upload(Patient $patient, string $contents, string $mimeType): Patient
    {
        $path = $this->folder($patient) . Str::uuid() . '.' . self::EXTENSIONS[$mimeType];

        if (! $this->disk()->put($path, $contents)) {
            // Falla del disco, no de negocio: un 500 genérico, y se reporta.
            throw new RuntimeException("No se pudo guardar la foto del paciente {$patient->id}.");
        }

        try {
            DB::transaction(function () use ($patient, $path) {
                $previous = $this->lockedPhotoPath($patient);

                $patient->photo_path = $path;
                $patient->save();

                if ($previous !== null && $this->belongsTo($patient, $previous)) {
                    $this->deleteAfterCommit($patient, $previous);
                }
            });
        } catch (Throwable $e) {
            $this->deleteOrWarn($patient, $path, 'la subida falló');

            throw $e;
        }

        return $patient;
    }

    public function delete(Patient $patient): Patient
    {
        DB::transaction(function () use ($patient) {
            $previous = $this->lockedPhotoPath($patient);

            if ($previous === null || ! $this->belongsTo($patient, $previous)) {
                throw $this->notFound();
            }

            $patient->photo_path = null;
            $patient->save();

            $this->deleteAfterCommit($patient, $previous);
        });

        return $patient;
    }

    /**
     * El archivo, con el tipo guardado, nosniff, una CSP que no deja ejecutar nada
     * y sin caché. Un photo_path sin archivo en el disco es un 404, con un aviso.
     */
    public function download(Patient $patient): StreamedResponse
    {
        $path = $patient->photo_path;

        if ($path === null || ! $this->belongsTo($patient, $path)) {
            throw $this->notFound();
        }

        if (! $this->disk()->exists($path)) {
            Log::warning('Foto de paciente sin archivo en el disco.', $this->context($patient, $path));

            throw $this->notFound();
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $type = array_search($extension, self::EXTENSIONS, true) ?: 'application/octet-stream';

        return $this->disk()->response($path, "{$patient->code}.{$extension}", [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * clinics/{clinic_id}/patients/{patient_id}/, con la barra final.
     */
    private function folder(Patient $patient): string
    {
        return "clinics/{$patient->clinic_id}/patients/{$patient->id}/";
    }

    /**
     * true si la ruta es un archivo de la carpeta del paciente; si no, lo registra.
     */
    private function belongsTo(Patient $patient, string $path): bool
    {
        $folder = $this->folder($patient);
        $name = substr($path, strlen($folder));

        if (str_starts_with($path, $folder) && preg_match('/^[0-9a-f-]{36}\.(jpg|png|webp)$/', $name) === 1) {
            return true;
        }

        Log::warning('Foto de paciente con una ruta fuera de su carpeta.', $this->context($patient, $path));

        return false;
    }

    /**
     * photo_path leído con la fila del paciente bloqueada, dentro de la transacción.
     */
    private function lockedPhotoPath(Patient $patient): ?string
    {
        return Patient::query()->whereKey($patient->getKey())->lockForUpdate()->value('photo_path');
    }

    /**
     * Si la transacción se revierte, el archivo anterior queda: sigue siendo la foto.
     */
    private function deleteAfterCommit(Patient $patient, string $path): void
    {
        DB::afterCommit(fn () => $this->deleteOrWarn($patient, $path, 'después del commit'));
    }

    /**
     * El disco no lanza excepciones (`throw` => false): un borrado que falla
     * devuelve false y deja el archivo huérfano. Queda en el log (deuda 14).
     */
    private function deleteOrWarn(Patient $patient, string $path, string $moment): void
    {
        if (! $this->disk()->delete($path)) {
            Log::warning("No se pudo borrar un archivo de foto de paciente ({$moment}).", $this->context($patient, $path));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Patient $patient, string $path): array
    {
        return [
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'photo_path' => $path,
        ];
    }

    private function notFound(): PatientException
    {
        return new PatientException(self::NOT_FOUND, 404, 'PATIENT_PHOTO_NOT_FOUND');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
