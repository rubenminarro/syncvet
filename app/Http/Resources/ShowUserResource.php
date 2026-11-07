<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un usuario (FASE 2, entrega 2.2a; is_active desde la 2.2b, en
 * UserResource). Con la 2.3 suma su ficha de veterinario.
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
