<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un servicio (FASE 2, entrega 2.4): suma la descripción y las fechas.
 */
class ShowServiceResource extends ServiceResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
