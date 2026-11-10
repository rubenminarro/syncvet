<?php

namespace App\Http\Resources;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agenda en los listados (FASE 3, entrega 3.1): la sucursal, el veterinario (con el nombre
 * de su usuario) y el servicio, o null. online_booking no se muestra. Espera branch,
 * veterinarian.user y service cargados (with) para no consultar por fila.
 *
 * @mixin Schedule
 */
class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
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
            'slot_minutes' => $this->slot_minutes,
            'color' => $this->color,
            'is_active' => $this->is_active,
        ];
    }
}
