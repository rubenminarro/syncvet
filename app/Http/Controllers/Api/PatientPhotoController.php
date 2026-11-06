<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPatientPhotoRequest;
use App\Http\Resources\ShowPatientResource;
use App\Models\Patient;
use App\Services\PatientPhotoService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * patients/{patient}/photo: subir, ver y borrar la foto del paciente (FASE 1,
 * entrega 1.5). La descarga es la única respuesta de la API que no usa ApiResponse
 * (es el archivo); sus errores sí (CLAUDE.md §4).
 */
class PatientPhotoController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected PatientPhotoService $photoService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:patients.upload-photo', only: ['store']),
            new Middleware('permission:patients.show-photo', only: ['show']),
            new Middleware('permission:patients.delete-photo', only: ['destroy']),
        ];
    }

    public function store(UploadPatientPhotoRequest $request, Patient $patient)
    {
        $patient = $this->photoService->upload($patient, $request->encodedPhoto(), $request->file('photo')->getMimeType());

        return $this->successResponse(
            'Foto del paciente guardada correctamente.',
            new ShowPatientResource($patient->loadMissing(['client', 'species', 'breed'])),
            200
        );
    }

    public function show(Patient $patient)
    {
        return $this->photoService->download($patient);
    }

    public function destroy(Patient $patient)
    {
        $patient = $this->photoService->delete($patient);

        return $this->successResponse(
            'Foto del paciente eliminada correctamente.',
            new ShowPatientResource($patient->loadMissing(['client', 'species', 'breed'])),
            200
        );
    }
}
