<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexScheduleRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\ScheduleAvailabilityRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Http\Resources\ShowScheduleResource;
use App\Models\Schedule;
use App\Services\ScheduleAvailabilityService;
use App\Services\ScheduleService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Agendas (FASE 3, entrega 3.1; P9): las arma solo el administrador; la recepción y el
 * veterinario listan y ven el detalle (schedules.index y schedules.show). El listado trae
 * las activas, salvo include_inactive=1. La disponibilidad (3.4), los tres roles
 * (schedules.availability).
 */
class ScheduleController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ScheduleService $scheduleService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:schedules.index', only: ['index']),
            new Middleware('permission:schedules.store', only: ['store']),
            new Middleware('permission:schedules.show', only: ['show']),
            new Middleware('permission:schedules.update', only: ['update']),
            new Middleware('permission:schedules.destroy', only: ['destroy']),
            new Middleware('permission:schedules.availability', only: ['availability']),
        ];
    }

    public function index(IndexScheduleRequest $request)
    {
        $schedules = Schedule::query()
            ->with(['branch', 'veterinarian.user', 'service'])
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('veterinarian_id'), fn ($query) => $query->where('veterinarian_id', $request->integer('veterinarian_id')))
            ->when($request->filled('service_id'), fn ($query) => $query->where('service_id', $request->integer('service_id')))
            // Búsqueda literal: "%" y "_" no son comodines (R-021).
            ->when($request->filled('search'), fn ($query) => $query->whereContains('name', $request->search))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Agendas obtenidas correctamente.',
            ScheduleResource::collection($schedules->items()),
            200,
            [
                'pagination' => [
                    'total' => $schedules->total(),
                    'perPage' => $schedules->perPage(),
                    'currentPage' => $schedules->currentPage(),
                    'lastPage' => $schedules->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreScheduleRequest $request)
    {
        $schedule = $this->scheduleService->create($request->validated());

        return $this->successResponse(
            'Agenda creada correctamente.',
            new ShowScheduleResource($this->scheduleService->loadDetail($schedule)),
            201
        );
    }

    public function show(Schedule $schedule)
    {
        return $this->successResponse(
            'Agenda encontrada.',
            new ShowScheduleResource($this->scheduleService->loadDetail($schedule)),
            200
        );
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $schedule = $this->scheduleService->update($schedule, $request->validated());

        return $this->successResponse(
            'Agenda actualizada correctamente.',
            new ShowScheduleResource($this->scheduleService->loadDetail($schedule)),
            200
        );
    }

    public function destroy(Schedule $schedule)
    {
        $this->scheduleService->delete($schedule);

        return $this->successResponse('Agenda eliminada correctamente.', null, 200);
    }

    /**
     * Los horarios libres de la agenda (FASE 3, entrega 3.4), con las reglas del alta de un
     * turno. Una agenda inactiva, o con su veterinario inactivo, → 422 en schedule.
     */
    public function availability(ScheduleAvailabilityRequest $request, Schedule $schedule, ScheduleAvailabilityService $availability)
    {
        [$from, $to] = $request->days();

        return $this->successResponse(
            'Disponibilidad obtenida correctamente.',
            $availability->days(
                $schedule,
                $from,
                $to,
                $request->filled('service_id') ? $request->integer('service_id') : null,
                $request->filled('duration_minutes') ? $request->integer('duration_minutes') : null
            ),
            200
        );
    }
}
