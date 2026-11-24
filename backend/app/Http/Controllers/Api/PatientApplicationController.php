<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPatientApplicationRequest;
use App\Http\Requests\IndexPatientApplicationRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StorePatientApplicationRequest;
use App\Http\Resources\PatientApplicationResource;
use App\Models\Patient;
use App\Models\PatientApplication;
use App\Services\PatientApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Las aplicaciones de un paciente (FASE 4, entrega 4.4): el listado (los tres roles), cargar (el
 * administrador y el veterinario) y anular (patient-applications.cancel: el administrador y el
 * veterinario, con las reglas de PatientApplicationService). Sin baja: una aplicación se anula.
 */
class PatientApplicationController extends Controller implements HasMiddleware
{
    use ApiResponse;

    private const RELATIONS = ['consultation', 'veterinarian.user', 'createdBy', 'cancelledBy'];

    public function __construct(
        protected PatientApplicationService $applications
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:patient-applications.index', only: ['index']),
            new Middleware('permission:patient-applications.store', only: ['store']),
            new Middleware('permission:patient-applications.cancel', only: ['cancel']),
        ];
    }

    /**
     * Las del paciente (también de un fallecido), de la más reciente a la más vieja; las anuladas,
     * marcadas.
     */
    public function index(IndexPatientApplicationRequest $request, Patient $patient)
    {
        [$from, $to] = $request->range();

        $applications = PatientApplication::query()
            ->with(self::RELATIONS)
            ->where('patient_id', $patient->getKey())
            ->when($request->filled('type'), fn ($query) => $query->whereIn('type', $request->validated('type')))
            ->when($from !== null, fn ($query) => $query->where('applied_at', '>=', $from->format('Y-m-d H:i:s')))
            ->when($to !== null, fn ($query) => $query->where('applied_at', '<=', $to->format('Y-m-d H:i:s')))
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Aplicaciones obtenidas correctamente.',
            PatientApplicationResource::collection($applications->items()),
            200,
            $this->paginationMeta($applications)
        );
    }

    public function store(StorePatientApplicationRequest $request, Patient $patient)
    {
        $application = $this->applications->create($patient, $request->validated(), $request->user());

        return $this->successResponse(
            'Aplicación cargada correctamente.',
            new PatientApplicationResource($application->load(self::RELATIONS)),
            201
        );
    }

    public function cancel(CancelPatientApplicationRequest $request, PatientApplication $application)
    {
        $application = $this->applications->cancel($application, $request->validated('cancellation_reason'), $request->user());

        return $this->successResponse(
            'Aplicación anulada correctamente.',
            new PatientApplicationResource($application->load(self::RELATIONS)),
            200
        );
    }
}
