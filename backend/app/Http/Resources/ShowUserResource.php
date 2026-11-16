<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un usuario (FASE 2, entrega 2.2a; is_active desde la 2.2b, en
 * UserResource). La ficha de veterinario (2.3) está en UserResource desde la entrega
 * front-02, en el mismo lugar de las claves.
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
