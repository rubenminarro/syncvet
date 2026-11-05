<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\ShowPatientResource;
use App\Models\Client;
use App\Services\PatientService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * clients/{client}/patients: pacientes de un cliente (el client_id sale de la URL).
 * Editar, cambiar de dueño y eliminar se hacen en patients/{patient}.
 */
class ClientPatientController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected PatientService $patientService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:patients.index', only: ['index']),
            new Middleware('permission:patients.store', only: ['store']),
        ];
    }

    public function index(Client $client)
    {
        $patients = $client->patients()->with(['species', 'breed'])->orderBy('name')->get();

        return $this->successResponse(
            'Pacientes del cliente obtenidos correctamente.',
            PatientResource::collection($patients),
            200
        );
    }

    public function store(StorePatientRequest $request, Client $client)
    {
        $patient = $this->patientService->create($client, $request->validated());

        $patient->loadMissing(['client', 'species', 'breed']);

        return $this->successResponse(
            'Paciente creado correctamente.',
            new ShowPatientResource($patient),
            201
        );
    }
}
