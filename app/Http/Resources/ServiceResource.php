<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Servicio en los listados (FASE 2, entrega 2.4): la categoría y el estado con su
 * etiqueta en español, y la tasa de IVA como número (0, 5 o 10). El precio, como texto
 * con dos decimales (decimal:2), igual que los demás importes.
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => [
                'value' => $this->category->value,
                'label' => $this->category->label(),
            ],
            'duration_minutes' => $this->duration_minutes,
            'standard_price' => $this->standard_price,
            'tax_rate' => $this->tax_rate->value,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
        ];
    }
}
