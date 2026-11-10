<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de una agenda (FASE 3, entrega 3.1): suma el horario semanal (por día y hora,
 * con las horas "HH:MM" de reloj de Asunción) y los bloqueos que terminan desde hoy (hasta
 * 20). Espera hours y blocks cargados como los deja ScheduleService::loadDetail().
 */
class ShowScheduleResource extends ScheduleResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'hours' => $this->hours->map(fn ($hour) => [
                'id' => $hour->id,
                'weekday' => $hour->weekday,
                'starts_at' => substr($hour->starts_at, 0, 5),
                'ends_at' => substr($hour->ends_at, 0, 5),
            ])->values(),
            'upcoming_blocks' => ScheduleBlockResource::collection($this->blocks),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
