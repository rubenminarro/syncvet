<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * El bloqueo recién creado (FASE 3, entrega 3.1; pregunta 22) con los turnos que ocupan el
 * horario y se superponen con él (affected_appointments), para que la recepción los
 * reprograme. Un turno sin paciente ni cliente muestra sus datos de contacto (desde la 3.2a).
 */
class StoredScheduleBlockResource extends ScheduleBlockResource
{
    /**
     * @param Collection<int, Appointment> $affected
     */
    public function __construct(ScheduleBlock $block, private Collection $affected)
    {
        parent::__construct($block);
    }

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'affected_appointments' => $this->affected->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toISOString(),
                'ends_at' => $appointment->ends_at->toISOString(),
                'status' => [
                    'value' => $appointment->status->value,
                    'label' => $appointment->status->label(),
                ],
                'patient' => $appointment->patient === null ? null : [
                    'id' => $appointment->patient->id,
                    'code' => $appointment->patient->code,
                    'name' => $appointment->patient->name,
                ],
                'client' => $appointment->client === null ? null : [
                    'id' => $appointment->client->id,
                    'display_name' => $appointment->client->display_name,
                ],
                // Un turno sin cliente ni paciente (FASE 3, entrega 3.2a).
                'contact_name' => $appointment->contact_name,
                'contact_phone' => $appointment->contact_phone,
            ])->values(),
        ];
    }
}
