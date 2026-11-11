<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Http\Resources\SystemSettingsResource;
use App\Services\SystemSettingsService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * system-settings: la configuración de la clínica en contexto (FASE 2, entrega 2.5).
 * La leen los tres roles (los documentos y recibos llevan los datos de la clínica); la
 * edita el administrador.
 */
class SystemSettingsController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected SystemSettingsService $settingsService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:system-settings.show', only: ['show']),
            new Middleware('permission:system-settings.update', only: ['update']),
        ];
    }

    public function show()
    {
        return $this->successResponse('Configuración de la clínica.', new SystemSettingsResource($this->settingsService->snapshot()), 200);
    }

    public function update(UpdateSystemSettingsRequest $request)
    {
        $snapshot = $this->settingsService->update($request->validated());

        return $this->successResponse('Configuración actualizada correctamente.', new SystemSettingsResource($snapshot), 200);
    }
}
