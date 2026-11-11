<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * Detalle de un turno (FASE 3, entrega 3.2a): lo del listado más las notas, quién lo creó,
 * las horas de confirmación, fichado, atendido y ausente (3.2b) y cancelación (UTC, "Z"),
 * quién lo canceló y el motivo.
 * Espera además createdBy y cancelledBy cargados (AppointmentService::loadDetail).
 *
 * @mixin Appointment
 */
class ShowAppointmentResource extends AppointmentResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'notes' => $this->notes,
            'created_by' => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ],
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            // FASE 3, entrega 3.2b.
            'attended_at' => $this->attended_at?->toISOString(),
            'no_show_at' => $this->no_show_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by' => $this->cancelledBy === null ? null : [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ],
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
