<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexConsultationRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreConsultationRequest;
use App\Http\Requests\UpdateConsultationRequest;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\ShowConsultationResource;
use App\Models\Consultation;
use App\Services\ConsultationService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * La consulta (FASE 4, entrega 4.1): la abren y la editan el administrador y el veterinario; los
 * tres roles listan y ven el detalle, la recepción sin el contenido clínico (sin
 * consultations.show-clinical: ConsultationResource). Sin baja (DELETE → 405): una consulta se
 * anula (4.2). El cierre, las enmiendas y la anulación, en la 4.2.
 */
class ConsultationController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ConsultationService $consultationService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:consultations.index', only: ['index']),
            new Middleware('permission:consultations.store', only: ['store']),
            new Middleware('permission:consultations.show', only: ['show']),
            new Middleware('permission:consultations.update', only: ['update']),
        ];
    }

    /**
     * Las consultas del rango (por defecto, los últimos 30 días), de toda la clínica, de la más
     * reciente a la más vieja.
     */
    public function index(IndexConsultationRequest $request)
    {
        [$from, $to] = $request->range();
        $search = $request->input('search');

        $consultations = Consultation::query()
            ->with(['branch', 'patient', 'client', 'veterinarian.user'])
            ->where('consulted_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('consulted_at', '<=', $to->format('Y-m-d H:i:s'))
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
            ->when($request->filled('veterinarian_id'), fn ($query) => $query->where('veterinarian_id', $request->integer('veterinarian_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('appointment_id'), fn ($query) => $query->where('appointment_id', $request->integer('appointment_id')))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', $request->validated('status')))
            // Búsqueda literal: "%" y "_" no son comodines (R-021).
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->whereContains('code', $search)
                ->orWhereHas('patient', fn ($patient) => $patient->withTrashed()
                    ->where(fn ($patient) => $patient->whereContains('name', $search)->orWhereContains('code', $search)))
                ->orWhereHas('client', fn ($client) => $client->withTrashed()
                    ->where(fn ($client) => $client
                        ->whereContains('first_name', $search)
                        ->orWhereContains('last_name', $search)
                        ->orWhereContains('business_name', $search)
                        ->orWhereContains('document_number', $search)))))
            ->orderByDesc('consulted_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Consultas obtenidas correctamente.',
            ConsultationResource::collection($consultations->items()),
            200,
            $this->paginationMeta($consultations)
        );
    }

    public function store(StoreConsultationRequest $request)
    {
        $consultation = $this->consultationService->open($request->validated(), $request->user());

        return $this->successResponse(
            'Consulta abierta correctamente.',
            new ShowConsultationResource($this->consultationService->loadDetail($consultation)),
            201
        );
    }

    public function show(Consultation $consultation)
    {
        return $this->successResponse(
            'Consulta encontrada.',
            new ShowConsultationResource($this->consultationService->loadDetail($consultation)),
            200
        );
    }

    public function update(UpdateConsultationRequest $request, Consultation $consultation)
    {
        $consultation = $this->consultationService->update($consultation, $request->validated(), $request->user());

        return $this->successResponse(
            'Consulta actualizada correctamente.',
            new ShowConsultationResource($this->consultationService->loadDetail($consultation)),
            200
        );
    }
}
