<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAuditLogRequest;
use App\Http\Requests\ListFilters;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\ShowAuditLogResource;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Consulta de la auditoría (FASE 2, entrega 2.6; P14): solo el administrador. Sin rutas
 * de escritura: los registros son inmutables (AUDIT_LOG_IMMUTABLE).
 *
 * AuditLog tiene el ClinicScope: el listado y el route binding ven solo la clínica en
 * contexto, y los LOGIN_FAILED sin clínica (clinic_id nulo) nunca aparecen. El listado
 * no trae old_values ni new_values (pregunta 27): solo el detalle.
 */
class AuditLogController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:audit-logs.index', only: ['index']),
            new Middleware('permission:audit-logs.show', only: ['show']),
        ];
    }

    public function index(IndexAuditLogRequest $request)
    {
        $from = $request->fromDate();
        $to = $request->toDate();
        $format = (new AuditLog())->getDateFormat();

        $logs = AuditLog::query()
            ->with('user')
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from->format($format)))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to->format($format)))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->input('event')))
            ->when($request->filled('auditable_type'), fn ($query) => $query->where('auditable_type', $request->input('auditable_type')))
            ->when($request->filled('auditable_id'), fn ($query) => $query->where('auditable_id', $request->integer('auditable_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                // Búsqueda literal en el nombre o el email del usuario (también eliminado): "%" y
                // "_" no son comodines (R-021).
                $query->whereHas('user', fn ($q) => $q->where(fn ($q) => $q->whereContains('name', $search)->orWhereContains('email', $search)));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Registros de auditoría obtenidos correctamente.',
            AuditLogResource::collection($logs->items()),
            200,
            [
                'pagination' => [
                    'total' => $logs->total(),
                    'perPage' => $logs->perPage(),
                    'currentPage' => $logs->currentPage(),
                    'lastPage' => $logs->lastPage(),
                ],
            ]
        );
    }

    public function show(AuditLog $auditLog)
    {
        return $this->successResponse('Registro de auditoría obtenido correctamente.', new ShowAuditLogResource($auditLog->load('user')), 200);
    }
}
