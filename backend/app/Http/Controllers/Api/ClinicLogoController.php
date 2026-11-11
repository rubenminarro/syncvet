<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadClinicLogoRequest;
use App\Http\Resources\SystemSettingsResource;
use App\Services\ClinicLogoService;
use App\Services\SystemSettingsService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * system-settings/logo: subir, ver y borrar el logo de la clínica (FASE 2, entrega
 * 2.5). La descarga es el archivo, como la foto del paciente (no usa ApiResponse; sus
 * errores sí, CLAUDE.md §4). Lo ven los tres roles; lo cambia el administrador.
 */
class ClinicLogoController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ClinicLogoService $logoService,
        protected SystemSettingsService $settingsService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:system-settings.upload-logo', only: ['store']),
            new Middleware('permission:system-settings.show-logo', only: ['show']),
            new Middleware('permission:system-settings.delete-logo', only: ['destroy']),
        ];
    }

    public function store(UploadClinicLogoRequest $request)
    {
        $this->logoService->upload($request->encodedImage(), $request->detectedMimeType());

        return $this->successResponse('Logo de la clínica guardado correctamente.', new SystemSettingsResource($this->settingsService->snapshot()), 200);
    }

    public function show()
    {
        return $this->logoService->download();
    }

    public function destroy()
    {
        $this->logoService->delete();

        return $this->successResponse('Logo de la clínica eliminado correctamente.', new SystemSettingsResource($this->settingsService->snapshot()), 200);
    }
}
