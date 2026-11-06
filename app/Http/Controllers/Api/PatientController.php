<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePatientOwnerRequest;
use App\Http\Requests\IndexPatientRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\ShowPatientResource;
use App\Models\Client;
use App\Models\Patient;
use App\Services\PatientService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientController extends Controller implements HasMiddleware
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
            new Middleware('permission:patients.show', only: ['show']),
            new Middleware('permission:patients.update', only: ['update']),
            new Middleware('permission:patients.change-owner', only: ['changeOwner']),
            new Middleware('permission:patients.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexPatientRequest $request)
    {
        $patients = Patient::query()
            ->with(['species', 'breed', 'client'])
            ->when(! $request->boolean('include_deceased'), fn ($q) => $q->whereNull('deceased_at'))
            ->when($request->filled('species_id'), fn ($q) => $q->where('species_id', $request->species_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('microchip', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Pacientes obtenidos correctamente.',
            PatientResource::collection($patients->items()),
            200,
            [
                'pagination' => [
                    'total' => $patients->total(),
                    'perPage' => $patients->perPage(),
                    'currentPage' => $patients->currentPage(),
                    'lastPage' => $patients->lastPage(),
                ],
            ]
        );
    }

    public function show(Patient $patient)
    {
        $patient->loadMissing(['client', 'species', 'breed']);

        return $this->successResponse(
            'Paciente encontrado.',
            new ShowPatientResource($patient),
            200
        );
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $patient = $this->patientService->update($patient, $request->validated());

        $patient->loadMissing(['client', 'species', 'breed']);

        return $this->successResponse(
            'Paciente actualizado correctamente.',
            new ShowPatientResource($patient),
            200
        );
    }

    public function changeOwner(ChangePatientOwnerRequest $request, Patient $patient)
    {
        $patient = $this->patientService->changeOwner($patient, Client::findOrFail($request->validated('client_id')));

        $patient->load(['client', 'species', 'breed']);

        return $this->successResponse(
            'Dueño del paciente actualizado correctamente.',
            new ShowPatientResource($patient),
            200
        );
    }

    public function destroy(Patient $patient)
    {
        $this->patientService->delete($patient);

        return $this->successResponse(
            'Paciente eliminado correctamente.',
            null,
            200
        );
    }
}
