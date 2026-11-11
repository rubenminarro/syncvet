<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentQueueRequest;
use App\Http\Requests\IndexAppointmentRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\QueueAppointmentResource;
use App\Http\Resources\ShowAppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentQueueService;
use App\Services\AppointmentService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Turnos (FASE 3, entrega 3.2a): los dan y los mueven el administrador y la recepción; los
 * tres roles listan y ven el detalle. Sin baja (DELETE → 405): un turno se cancela (3.2b). El
 * sobreturno (overbook: true) pide además appointments.overbook, en el authorize() de su
 * Form Request.
 */
class AppointmentController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected AppointmentService $appointmentService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:appointments.index', only: ['index']),
            new Middleware('permission:appointments.store', only: ['store']),
            new Middleware('permission:appointments.show', only: ['show']),
            new Middleware('permission:appointments.update', only: ['update']),
            new Middleware('permission:appointments.queue', only: ['queue']),
        ];
    }

    /**
     * La cola del día (FASE 3, entrega 3.4; P8): los turnos del día en la sucursal actual del
     * usuario, sin los cancelados, sin paginar. meta: el día, la sucursal, el veterinario por el
     * que se filtró (el de la ficha del usuario por defecto; pregunta 25), includes_unassigned (la
     * vista por defecto de una ficha suma las agendas sin veterinario; D1) y truncated (cortó en
     * AppointmentQueueService::LIMIT; D3).
     */
    public function queue(AppointmentQueueRequest $request, AppointmentQueueService $queue)
    {
        $day = $request->day();
        $user = $request->user();

        $result = $queue->forUser(
            $user,
            $day,
            $request->filled('veterinarian_id') ? $request->integer('veterinarian_id') : null,
            $request->filled('schedule_id') ? $request->integer('schedule_id') : null,
            $request->boolean('all')
        );

        return $this->successResponse(
            'Cola del día obtenida correctamente.',
            QueueAppointmentResource::collection($result['appointments']),
            200,
            [
                'date' => $day->toDateString(),
                'branch_id' => (int) $user->current_branch_id,
                'veterinarian_id' => $result['veterinarian_id'],
                'includes_unassigned' => $result['includes_unassigned'],
                'truncated' => $result['truncated'],
            ]
        );
    }

    /**
     * Los turnos que empiezan en el rango (por defecto, hoy en Asunción).
     */
    public function index(IndexAppointmentRequest $request)
    {
        [$from, $to] = $request->range();

        $appointments = Appointment::query()
            ->with(['schedule', 'branch', 'veterinarian.user', 'service', 'patient', 'client'])
            ->where('starts_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('starts_at', '<=', $to->format('Y-m-d H:i:s'))
            ->when($request->filled('schedule_id'), fn ($query) => $query->where('schedule_id', $request->integer('schedule_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('veterinarian_id'), fn ($query) => $query->where('veterinarian_id', $request->integer('veterinarian_id')))
            ->when($request->filled('service_id'), fn ($query) => $query->where('service_id', $request->integer('service_id')))
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', $request->validated('status')))
            ->when($request->filled('series_id'), fn ($query) => $query->where('series_id', $request->input('series_id')))
            // Búsqueda literal: "%" y "_" no son comodines (R-021).
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->whereContains('contact_name', $request->search)
                ->orWhereContains('contact_phone', $request->search)
                ->orWhereHas('patient', fn ($patient) => $patient->withTrashed()
                    ->where(fn ($patient) => $patient->whereContains('name', $request->search)->orWhereContains('code', $request->search)))
                ->orWhereHas('client', fn ($client) => $client->withTrashed()
                    ->where(fn ($client) => $client
                        ->whereContains('first_name', $request->search)
                        ->orWhereContains('last_name', $request->search)
                        ->orWhereContains('business_name', $request->search)
                        ->orWhereContains('document_number', $request->search)))))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Turnos obtenidos correctamente.',
            AppointmentResource::collection($appointments->items()),
            200,
            [
                'pagination' => [
                    'total' => $appointments->total(),
                    'perPage' => $appointments->perPage(),
                    'currentPage' => $appointments->currentPage(),
                    'lastPage' => $appointments->lastPage(),
                ],
            ]
        );
    }

    /**
     * Un turno, o una serie si llega recurrence (3.3): 201 con la lista de los turnos creados
     * (AppointmentResource) y meta.series_id.
     */
    public function store(StoreAppointmentRequest $request)
    {
        if ($request->validated('recurrence') !== null) {
            $series = $this->appointmentService->createSeries($request->validated())
                ->load(['schedule', 'branch', 'veterinarian.user', 'service', 'patient', 'client']);

            return $this->successResponse(
                "Serie de {$series->count()} turnos creada correctamente.",
                AppointmentResource::collection($series),
                201,
                ['series_id' => $series->first()->series_id]
            );
        }

        $appointment = $this->appointmentService->create($request->validated());

        return $this->successResponse(
            'Turno creado correctamente.',
            new ShowAppointmentResource($this->appointmentService->loadDetail($appointment)),
            201
        );
    }

    public function show(Appointment $appointment)
    {
        return $this->successResponse(
            'Turno encontrado.',
            new ShowAppointmentResource($this->appointmentService->loadDetail($appointment)),
            200
        );
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointmentService->update($appointment, $request->validated());

        return $this->successResponse(
            'Turno actualizado correctamente.',
            new ShowAppointmentResource($this->appointmentService->loadDetail($appointment)),
            200
        );
    }
}
