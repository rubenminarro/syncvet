<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPatientHistoryRequest;
use App\Http\Resources\PatientHistoryResource;
use App\Models\Patient;
use App\Services\PatientHistoryService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * El historial del paciente (FASE 4, entrega 4.6): GET patients/{patient}/history. Sin permiso propio:
 * exige los dos que reúne, consultations.index y patient-applications.index (dos middleware: los dos a
 * la vez, no uno u otro; R-099). Los tres roles los tienen; la recepción ve el resumen administrativo de
 * las consultas (PatientHistoryResource).
 */
class PatientHistoryController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected PatientHistoryService $history
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:consultations.index'),
            new Middleware('permission:patient-applications.index'),
        ];
    }

    public function index(IndexPatientHistoryRequest $request, Patient $patient)
    {
        $page = $this->history->paginate($patient, $request->perPage());

        return $this->successResponse(
            'Historial obtenido correctamente.',
            PatientHistoryResource::collection($page->items()),
            200,
            $this->paginationMeta($page)
        );
    }
}
