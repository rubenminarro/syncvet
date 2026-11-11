<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un registro de auditoría en el listado (FASE 2, entrega 2.6): sin old_values ni
 * new_values (pregunta 27; están en ShowAuditLogResource). El usuario, también
 * eliminado, con su id y su nombre; null en un registro sin usuario.
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => [
                'value' => $this->event->value,
                'label' => $this->event->label(),
            ],
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
