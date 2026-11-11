<?php

namespace App\Http\Resources;

use App\Models\ScheduleBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bloqueo de una agenda (FASE 3, entrega 3.1): los instantes en UTC ("Z") y quién lo creó.
 * Espera createdBy cargado (with).
 *
 * @mixin ScheduleBlock
 */
class ScheduleBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'reason' => $this->reason,
            'created_by' => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
