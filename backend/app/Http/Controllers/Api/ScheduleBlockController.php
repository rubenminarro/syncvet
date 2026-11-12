<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexScheduleBlockRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreScheduleBlockRequest;
use App\Http\Resources\ScheduleBlockResource;
use App\Http\Resources\StoredScheduleBlockResource;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Services\ScheduleBlockService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Bloqueos de una agenda (FASE 3, entrega 3.1): se listan y se crean en
 * schedules/{schedule}/blocks y se eliminan en schedule-blocks/{scheduleBlock}. Los crea y
 * los elimina el administrador; los tres roles los listan (schedule-blocks.index).
 */
class ScheduleBlockController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ScheduleBlockService $scheduleBlockService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:schedule-blocks.index', only: ['index']),
            new Middleware('permission:schedule-blocks.store', only: ['store']),
            new Middleware('permission:schedule-blocks.destroy', only: ['destroy']),
        ];
    }

    /**
     * Sin from ni to, los que terminan desde hoy (Asunción). Con from, los que terminan desde
     * ese momento; con to, los que empiezan hasta ese momento.
     */
    public function index(IndexScheduleBlockRequest $request, Schedule $schedule)
    {
        $from = $request->fromDate();
        $to = $request->toDate();

        if ($from === null && $to === null) {
            $from = now()->toImmutable()->startOfDay();
        }

        $blocks = $schedule->blocks()
            ->with('createdBy')
            ->when($from !== null, fn ($query) => $query->where('ends_at', '>=', $from->format('Y-m-d H:i:s')))
            ->when($to !== null, fn ($query) => $query->where('starts_at', '<=', $to->format('Y-m-d H:i:s')))
            // Búsqueda literal: "%" y "_" no son comodines (R-021).
            ->when($request->filled('search'), fn ($query) => $query->whereContains('reason', $request->search))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Bloqueos obtenidos correctamente.',
            ScheduleBlockResource::collection($blocks->items()),
            200,
            $this->paginationMeta($blocks)
        );
    }

    public function store(StoreScheduleBlockRequest $request, Schedule $schedule)
    {
        [$block, $affected] = $this->scheduleBlockService->create($schedule, [
            'starts_at' => $request->startsAt(),
            'ends_at' => $request->endsAt(),
            'reason' => $request->validated('reason'),
        ]);

        return $this->successResponse(
            'Bloqueo creado correctamente.',
            new StoredScheduleBlockResource($block->load('createdBy'), $affected),
            201
        );
    }

    public function destroy(ScheduleBlock $scheduleBlock)
    {
        $this->scheduleBlockService->delete($scheduleBlock);

        return $this->successResponse('Bloqueo eliminado correctamente.', null, 200);
    }
}
