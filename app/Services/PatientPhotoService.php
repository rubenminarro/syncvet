<?php

namespace App\Services;

use App\Exceptions\PatientException;
use App\Models\Patient;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Foto del paciente (FASE 1, entrega 1.5). Disco `local` (privado, fuera de
 * public/), en clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}: el nombre es
 * al azar y la extensión sale del tipo detectado en el contenido, nunca del archivo
 * subido. Sin redimensionar (R-015). La foto no es un dato clínico: un paciente
 * fallecido también la cambia o la borra (pregunta 12 del diseño).
 *
 * El cambio de photo_path va en una transacción con la fila del paciente bloqueada
 * (dos subidas simultáneas no dejan un archivo huérfano). El archivo anterior se
 * borra después del commit; si la transacción falla, se borra el nuevo.
 */
class PatientPhotoService
{
    public const DISK = 'local';

    public const NOT_FOUND = 'El paciente no tiene foto.';

    /**
     * Tipo detectado → extensión del archivo guardado.
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function upload(Patient $patient, UploadedFile $file): Patient
    {
        $extension = self::EXTENSIONS[$file->getMimeType()];
        $path = $this->disk()->putFileAs(
            "clinics/{$patient->clinic_id}/patients/{$patient->id}",
            $file,
            Str::uuid() . ".{$extension}"
        );

        if ($path === false) {
            // Falla del disco, no de negocio: un 500 genérico, y se reporta.
            throw new RuntimeException("No se pudo guardar la foto del paciente {$patient->id}.");
        }

        try {
            DB::transaction(function () use ($patient, $path) {
                $previous = $this->lockedPhotoPath($patient);

                $patient->photo_path = $path;
                $patient->save();

                $this->deleteAfterCommit($previous);
            });
        } catch (Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }

        return $patient;
    }

    public function delete(Patient $patient): Patient
    {
        DB::transaction(function () use ($patient) {
            $previous = $this->lockedPhotoPath($patient);

            if ($previous === null) {
                throw $this->notFound();
            }

            $patient->photo_path = null;
            $patient->save();

            $this->deleteAfterCommit($previous);
        });

        return $patient;
    }

    /**
     * El archivo, con el tipo guardado, nosniff y sin caché. Un photo_path sin
     * archivo en el disco es un 404, con un aviso en el log.
     */
    public function download(Patient $patient): StreamedResponse
    {
        $path = $patient->photo_path;

        if ($path === null) {
            throw $this->notFound();
        }

        if (! $this->disk()->exists($path)) {
            Log::warning('Foto de paciente sin archivo en el disco.', [
                'clinic_id' => $patient->clinic_id,
                'patient_id' => $patient->id,
                'photo_path' => $path,
            ]);

            throw $this->notFound();
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $type = array_search($extension, self::EXTENSIONS, true) ?: 'application/octet-stream';

        return $this->disk()->response($path, "{$patient->code}.{$extension}", [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
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
    private function deleteAfterCommit(?string $path): void
    {
        if ($path !== null) {
            DB::afterCommit(fn () => $this->disk()->delete($path));
        }
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
