<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * Turno en la cola del día (FASE 3, entrega 3.4): el de los listados, con el paciente ampliado
 * (especie y raza) y checked_in_at (la hora en que se fichó, para la demora), en UTC ("Z").
 * Espera además patient.species y patient.breed cargados (AppointmentQueueService::RELATIONS).
 *
 * @mixin Appointment
 */
class QueueAppointmentResource extends AppointmentResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->patient !== null) {
            $data['patient'] = [
                ...$data['patient'],
                'species' => $this->patient->species === null ? null : [
                    'id' => $this->patient->species->id,
                    'name' => $this->patient->species->name,
                ],
                'breed' => $this->patient->breed === null ? null : [
                    'id' => $this->patient->breed->id,
                    'name' => $this->patient->breed->name,
                ],
            ];
        }

        $data['checked_in_at'] = $this->checked_in_at?->toISOString();

        return $data;
    }
}
