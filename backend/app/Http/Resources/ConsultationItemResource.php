<?php

namespace App\Http\Resources;

use App\Models\ConsultationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un ítem de la consulta (FASE 4, entrega 4.3), en el detalle de la consulta: lo ve también la
 * recepción (son para cobrar, pregunta 7). El tipo, el servicio, el veterinario de los honorarios
 * (o null), la descripción, la cantidad, el precio unitario, el descuento y el total (strings
 * decimales), la tasa de IVA y el estado de cobro ({value, label}). Espera service y
 * veterinarian.user cargados.
 *
 * @mixin ConsultationItem
 */
class ConsultationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'service' => $this->service === null ? null : [
                'id' => $this->service->id,
                'code' => $this->service->code,
                'name' => $this->service->name,
            ],
            'veterinarian' => $this->veterinarian === null ? null : [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
                'name' => $this->veterinarian->user?->name,
            ],
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'total' => $this->total,
            'tax_rate' => [
                'value' => $this->tax_rate->value,
                'label' => $this->tax_rate->label(),
            ],
            'billing_status' => [
                'value' => $this->billing_status->value,
                'label' => $this->billing_status->label(),
            ],
        ];
    }
}
