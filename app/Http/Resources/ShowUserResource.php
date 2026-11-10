<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un usuario (FASE 2, entrega 2.2a; is_active desde la 2.2b, en
 * UserResource). Con la 2.3 suma su ficha de veterinario (id y código; null sin ficha
 * o con la ficha eliminada).
 */
class ShowUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'veterinarian' => $this->veterinarian ? [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
