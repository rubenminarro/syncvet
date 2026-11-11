<?php

namespace App\Services;

use App\Exceptions\PatientException;
use App\Models\Patient;
use App\Support\PrivateImageStore;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
 * que falla queda en el log, y lo levanta files:prune-orphans (deuda 14).
 *
 * photo_path solo se usa si está en la carpeta del paciente: otro valor (no lo
 * escribe la API; R-027) responde 404 al verla o borrarla, no se borra al
 * reemplazarla, y queda en el log.
 *
 * Lo común con el logo de la clínica (guardar, reemplazar, validar la ruta y
 * descargar) está en PrivateImageStore (FASE 2, entrega 2.5).
 */
class PatientPhotoService
{
    public const DISK = 'patient_photos';

    public const NOT_FOUND = 'El paciente no tiene foto.';

    /**
     * Tipo detectado → extensión del archivo guardado.
     */
    public const EXTENSIONS = PrivateImageStore::EXTENSIONS;

    /**
     * $contents: la imagen ya codificada de nuevo; $mimeType: el tipo detectado.
     */
    public function upload(Patient $patient, string $contents, string $mimeType): Patient
    {
        $this->store()->replace($this->folder($patient), $contents, $mimeType, $this->context($patient), function (string $path) use ($patient) {
            $previous = $this->lockedPhotoPath($patient);

            $patient->photo_path = $path;
            $patient->save();

            return $previous;
        });

        return $patient;
    }

    public function delete(Patient $patient): Patient
    {
        DB::transaction(function () use ($patient) {
            $previous = $this->lockedPhotoPath($patient);

            if ($previous === null || ! $this->store()->owns($this->folder($patient), $previous, $this->context($patient))) {
                throw $this->notFound();
            }

            $patient->photo_path = null;
            $patient->save();

            $this->store()->deleteAfterCommit($previous, $this->context($patient));
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

        if ($path === null || ! $this->store()->owns($this->folder($patient), $path, $this->context($patient))) {
            throw $this->notFound();
        }

        return $this->store()->response($path, $patient->code, $this->context($patient)) ?? throw $this->notFound();
    }

    /**
     * clinics/{clinic_id}/patients/{patient_id}/, con la barra final.
     */
    public static function folder(Patient $patient): string
    {
        return "clinics/{$patient->clinic_id}/patients/{$patient->id}/";
    }

    /**
     * photo_path leído con la fila del paciente bloqueada, dentro de la transacción.
     */
    private function lockedPhotoPath(Patient $patient): ?string
    {
        return Patient::query()->whereKey($patient->getKey())->lockForUpdate()->value('photo_path');
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Patient $patient): array
    {
        return [
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
        ];
    }

    private function notFound(): PatientException
    {
        return new PatientException(self::NOT_FOUND, 404, 'PATIENT_PHOTO_NOT_FOUND');
    }

    private function store(): PrivateImageStore
    {
        return new PrivateImageStore(self::DISK, 'Foto de paciente', 'photo_path');
    }
}
