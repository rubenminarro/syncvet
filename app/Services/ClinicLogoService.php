<?php

namespace App\Services;

use App\Enums\SystemSettingKey;
use App\Exceptions\ClinicException;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use App\Support\PrivateImageStore;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo de la clínica (FASE 2, entrega 2.5; P12 y pregunta 25). Disco propio
 * `clinic_logos` (privado, sin `serve`), en clinics/{clinic_id}/logo/{uuid}.{ext}, con
 * las reglas de la foto del paciente (PrivateImageStore): la imagen vuelta a codificar
 * por el Form Request, el anterior borrado después del commit, el nuevo borrado si la
 * transacción falla, y una ruta fuera de la carpeta o sin archivo → 404
 * CLINIC_LOGO_NOT_FOUND con un aviso en el log.
 *
 * La ruta va en la clave interna logo_path de system_settings y nunca sale de la API:
 * has_logo en la configuración y en `me`. La subida y el borrado bloquean la fila de la
 * clínica antes de leer logo_path (clínica → configuración, como SystemSettingsService).
 */
class ClinicLogoService
{
    public const DISK = 'clinic_logos';

    public const NOT_FOUND = 'La clínica no tiene logo.';

    public function __construct(
        protected SystemSettingsService $settings
    ) {
    }

    /**
     * $contents: la imagen ya codificada de nuevo; $mimeType: el tipo detectado.
     */
    public function upload(string $contents, string $mimeType): void
    {
        $clinicId = CurrentClinic::id();

        $this->store()->replace(self::folder($clinicId), $contents, $mimeType, $this->context($clinicId), function (string $path) {
            $previous = $this->lockedLogoPath();
            $this->settings->put(SystemSettingKey::LOGO_PATH, $path);

            return $previous;
        });
    }

    public function delete(): void
    {
        $clinicId = CurrentClinic::id();

        DB::transaction(function () use ($clinicId) {
            $previous = $this->lockedLogoPath();

            if ($previous === null || ! $this->store()->owns(self::folder($clinicId), $previous, $this->context($clinicId))) {
                throw $this->notFound();
            }

            $this->settings->put(SystemSettingKey::LOGO_PATH, null);
            $this->store()->deleteAfterCommit($previous, $this->context($clinicId));
        });
    }

    /**
     * El archivo, como la foto del paciente: el tipo guardado, nosniff, CSP y sin caché.
     */
    public function download(): StreamedResponse
    {
        $clinicId = CurrentClinic::id();
        $path = $this->settings->get(SystemSettingKey::LOGO_PATH);

        if ($path === null || ! $this->store()->owns(self::folder($clinicId), $path, $this->context($clinicId))) {
            throw $this->notFound();
        }

        return $this->store()->response($path, 'logo', $this->context($clinicId)) ?? throw $this->notFound();
    }

    /**
     * Si la clínica tiene logo (para `me`, que se arma también en el login, sin la
     * clínica en contexto).
     */
    public function hasLogo(Clinic $clinic): bool
    {
        return CurrentClinic::run($clinic, fn () => $this->settings->get(SystemSettingKey::LOGO_PATH) !== null);
    }

    /**
     * clinics/{clinic_id}/logo/, con la barra final.
     */
    public static function folder(int $clinicId): string
    {
        return "clinics/{$clinicId}/logo/";
    }

    /**
     * logo_path leído con la fila de la clínica bloqueada, dentro de la transacción.
     */
    private function lockedLogoPath(): ?string
    {
        CatalogLock::forCurrentClinic();

        return $this->settings->get(SystemSettingKey::LOGO_PATH);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(int $clinicId): array
    {
        return ['clinic_id' => $clinicId];
    }

    private function notFound(): ClinicException
    {
        return new ClinicException(self::NOT_FOUND, 404, 'CLINIC_LOGO_NOT_FOUND');
    }

    private function store(): PrivateImageStore
    {
        return new PrivateImageStore(self::DISK, 'Logo de la clínica', 'logo_path');
    }
}
