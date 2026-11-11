<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Resources\ShowAppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\AppointmentTransitionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Estados del turno (FASE 3, entrega 3.2b): confirmar, fichar, atendido, ausente, deshacer el
 * ausente y cancelar. Cada uno con su permiso; deshacer el ausente usa appointments.no-show
 * (pregunta 19: lo deshacen el administrador y la recepción). Responden 200 con el detalle.
 */
class AppointmentTransitionController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected AppointmentTransitionService $transitions,
        protected AppointmentService $appointments
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:appointments.confirm', only: ['confirm']),
            new Middleware('permission:appointments.check-in', only: ['checkIn']),
            new Middleware('permission:appointments.attend', only: ['attend']),
            new Middleware('permission:appointments.no-show', only: ['noShow', 'undoNoShow']),
            new Middleware('permission:appointments.cancel', only: ['cancel']),
        ];
    }

    public function confirm(Appointment $appointment): JsonResponse
    {
        return $this->respond('Turno confirmado.', $this->transitions->confirm($appointment));
    }

    public function checkIn(Appointment $appointment): JsonResponse
    {
        return $this->respond('Turno fichado.', $this->transitions->checkIn($appointment));
    }

    public function attend(Appointment $appointment): JsonResponse
    {
        return $this->respond('Turno marcado como atendido.', $this->transitions->attend($appointment));
    }

    public function noShow(Appointment $appointment): JsonResponse
    {
        return $this->respond('Turno marcado como ausente.', $this->transitions->noShow($appointment));
    }

    public function undoNoShow(Appointment $appointment): JsonResponse
    {
        return $this->respond('Se deshizo el ausente: el turno volvió a confirmado.', $this->transitions->undoNoShow($appointment));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        return $this->respond('Turno cancelado.', $this->transitions->cancel($appointment, $request->validated('cancellation_reason')));
    }

    private function respond(string $message, Appointment $appointment): JsonResponse
    {
        return $this->successResponse($message, new ShowAppointmentResource($this->appointments->loadDetail($appointment)), 200);
    }
}
