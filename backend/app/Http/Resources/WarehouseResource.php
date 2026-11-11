<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Depósito de una sucursal (FASE 2, entrega 2.1). Sin Show…Resource: no tiene detalle.
 */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
