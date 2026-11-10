<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sucursal en los listados (FASE 2, entrega 2.1).
 */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'establishment' => $this->establishment,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
