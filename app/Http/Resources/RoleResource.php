<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un rol de la clínica (FASE 2, entrega 2.2a): su nombre y su etiqueta en español.
 * Recibe un UserRole.
 *
 * @property UserRole $resource
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->value,
            'label' => $this->resource->label(),
        ];
    }
}
