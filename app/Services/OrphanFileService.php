<?php

namespace App\Services;

use App\Enums\SystemSettingKey;
use App\Models\Clinic;
use App\Models\Patient;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Archivos huérfanos de las imágenes privadas (FASE 2, entrega 2.5; deuda 14, P15 y
 * pregunta 26): fotos de pacientes y logos que ya no son de nadie (un reemplazo cuyo
 * borrado falló, un proceso que murió antes del commit).
 *
 * Por cada clínica, con la clínica en contexto, solo mira su carpeta de fotos
 * (clinics/{id}/patients/ del disco patient_photos) y la de su logo (clinics/{id}/logo/
 * del disco clinic_logos). Nunca borra fuera de esas carpetas, aunque una ruta de la
 * base apunte a otro lugar: las rutas de la base solo sirven para conservar archivos.
 * Conserva el photo_path de cada paciente, también eliminado (P15), el logo_path de la
 * clínica y todo archivo de menos de 24 horas (una subida en curso).
 *
 * Cada clínica se revisa entera antes de borrar nada en ella: si su revisión falla (un
 * enlace simbólico en la carpeta, un error del disco), queda en el log, no se borra nada
 * en esa clínica y se sigue con las demás (corrección de la 2.5).
 *
 * --dry-run informa lo mismo que se borraría: la lista sale del mismo cálculo.
 */
class OrphanFileService
{
    public const MIN_AGE_HOURS = 24;

    /**
     * Borra los huérfanos (salvo $dryRun) y los devuelve, junto con los que no se
     * pudieron borrar y las clínicas que no se pudieron revisar. Cada borrado queda en el
     * log; uno que falla, como aviso, y no se cuenta como borrado.
     *
     * @return array{orphans: list<array{clinic_id: int, disk: string, path: string}>, failed: list<array{clinic_id: int, disk: string, path: string}>, failed_clinics: list<int>}
     */
    public function prune(bool $dryRun): array
    {
        $orphans = [];
        $failed = [];
        $failedClinics = [];
        $threshold = now()->subHours(self::MIN_AGE_HOURS)->getTimestamp();

        foreach (Clinic::query()->orderBy('id')->get() as $clinic) {
            try {
                $found = $this->orphansOf($clinic, $threshold);
            } catch (Throwable $e) {
                Log::error('No se pudieron revisar los archivos de una clínica: no se borró nada en ella.', [
                    'clinic_id' => $clinic->id,
                    'exception' => $e,
                ]);
                $failedClinics[] = $clinic->id;

                continue;
            }

            foreach ($found as $orphan) {
                $orphans[] = $orphan;

                if ($dryRun) {
                    continue;
                }

                if (Storage::disk($orphan['disk'])->delete($orphan['path'])) {
                    Log::info('Archivo huérfano borrado.', $orphan);
                } else {
                    Log::warning('No se pudo borrar un archivo huérfano.', $orphan);
                    $failed[] = $orphan;
                }
            }
        }

        return ['orphans' => $orphans, 'failed' => $failed, 'failed_clinics' => $failedClinics];
    }

    /**
     * Los huérfanos de una clínica: disco y ruta de cada uno.
     *
     * @return list<array{clinic_id: int, disk: string, path: string}>
     */
    private function orphansOf(Clinic $clinic, int $threshold): array
    {
        return CurrentClinic::run($clinic, function () use ($clinic, $threshold) {
            $orphans = [];
            $photos = Patient::withTrashed()->whereNotNull('photo_path')->pluck('photo_path')->all();
            $logo = app(SystemSettingsService::class)->get(SystemSettingKey::LOGO_PATH);

            $folders = [
                [PatientPhotoService::DISK, "clinics/{$clinic->id}/patients/", array_flip($photos)],
                [ClinicLogoService::DISK, ClinicLogoService::folder($clinic->id), $logo !== null ? [$logo => true] : []],
            ];

            foreach ($folders as [$disk, $folder, $kept]) {
                foreach ($this->oldFilesIn($disk, $folder, $threshold) as $path) {
                    if (! isset($kept[$path])) {
                        $orphans[] = ['clinic_id' => $clinic->id, 'disk' => $disk, 'path' => $path];
                    }
                }
            }

            return $orphans;
        });
    }

    /**
     * Los archivos de la carpeta (y sus subcarpetas) modificados antes del umbral. Solo
     * rutas dentro de la carpeta: el resto del disco no se mira.
     *
     * @return list<string>
     */
    private function oldFilesIn(string $disk, string $folder, int $threshold): array
    {
        $storage = Storage::disk($disk);

        return array_values(array_filter(
            $storage->allFiles(rtrim($folder, '/')),
            fn (string $path) => str_starts_with($path, $folder)
                && ! str_contains($path, '..')
                && $storage->lastModified($path) < $threshold
        ));
    }
}
