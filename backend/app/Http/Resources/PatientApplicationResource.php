<?php

namespace App\Http\Resources;

use App\Models\PatientApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una aplicación de un paciente (FASE 4, entrega 4.4), en el listado y al cargarla o anularla: el tipo
 * ({value, label}), el nombre, el lote y su vencimiento, la dosis, la vía, la fecha de aplicación (UTC,
 * "Z"), el número de dosis, la próxima dosis (fechas, AAAA-MM-DD), las notas, la consulta (o null), el
 * veterinario (o null), quién la cargó y, marcada, la anulación: cancelled (bool), cuándo, quién y por
 * qué. La ven los tres roles (patient-applications.index; la recepción también, pregunta 7). Espera
 * consultation, veterinarian.user, createdBy y cancelledBy cargados.
 *
 * @mixin PatientApplication
 */
class PatientApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'name' => $this->name,
            'lot_number' => $this->lot_number,
            'lot_expires_at' => $this->lot_expires_at?->toDateString(),
            'dose' => $this->dose,
            'route' => $this->route,
            'applied_at' => $this->applied_at->toISOString(),
            'dose_number' => $this->dose_number,
            'next_dose_due_at' => $this->next_dose_due_at?->toDateString(),
            'notes' => $this->notes,
            'consultation' => $this->consultation === null ? null : [
                'id' => $this->consultation->id,
                'code' => $this->consultation->code,
            ],
            'veterinarian' => $this->veterinarian === null ? null : [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
                'name' => $this->veterinarian->user?->name,
            ],
            'created_by' => $this->createdBy === null ? null : ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'cancelled' => $this->cancelled_at !== null,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by' => $this->cancelledBy === null ? null : ['id' => $this->cancelledBy->id, 'name' => $this->cancelledBy->name],
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
