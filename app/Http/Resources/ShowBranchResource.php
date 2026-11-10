<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de una sucursal, con todos sus depósitos (activos o no; FASE 2, entrega 2.1).
 */
class ShowBranchResource extends BranchResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'warehouses' => WarehouseResource::collection($this->whenLoaded('warehouses')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
