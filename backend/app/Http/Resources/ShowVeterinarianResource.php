<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle de un veterinario (FASE 2, entrega 2.3): suma la comisión (P7, sin
 * cálculos), las notas y las fechas.
 */
class ShowVeterinarianResource extends VeterinarianResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'commission_percentage' => $this->commission_percentage,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
