<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un usuario (FASE 2, entrega 2.2a). Con la 2.3 suma su ficha de
 * veterinario; con la 2.2b, is_active.
 */
class ShowUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
