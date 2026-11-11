<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Turno en los listados (FASE 3, entrega 3.2a): la agenda, la sucursal, el veterinario (con el
 * nombre de su usuario), el servicio, el paciente y el cliente (o null), el contacto, los
 * instantes en UTC ("Z"), el estado y el origen ({value, label}), el sobreturno y la serie.
 * Espera schedule, branch, veterinarian.user, service, patient y client cargados (with).
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule' => [
                'id' => $this->schedule->id,
                'name' => $this->schedule->name,
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ],
            'veterinarian' => $this->veterinarian === null ? null : [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
                'name' => $this->veterinarian->user->name,
            ],
            'service' => $this->service === null ? null : [
                'id' => $this->service->id,
                'code' => $this->service->code,
                'name' => $this->service->name,
            ],
            'patient' => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'code' => $this->patient->code,
                'name' => $this->patient->name,
            ],
            'client' => $this->client === null ? null : [
                'id' => $this->client->id,
                'display_name' => $this->client->display_name,
            ],
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'source' => [
                'value' => $this->source->value,
                'label' => $this->source->label(),
            ],
            'is_overbooked' => $this->is_overbooked,
            'series_id' => $this->series_id,
            'reason' => $this->reason,
        ];
    }
}
