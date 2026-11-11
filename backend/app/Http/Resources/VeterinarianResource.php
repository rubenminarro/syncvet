<?php

namespace App\Http\Resources;

use App\Models\Veterinarian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Veterinario en los listados (FASE 2, entrega 2.3): su usuario (con is_active: un
 * usuario inactivo no se asigna en las fases 3 y 4) y sus sucursales, las del usuario
 * (P5). Espera user.branches cargado (with) para no consultar por fila.
 *
 * @mixin Veterinarian
 */
class VeterinarianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'is_active' => (bool) $this->user->is_active,
            ],
            'license_number' => $this->license_number,
            'specialty' => $this->specialty,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'branches' => $this->user->branches->sortBy('code')->values()->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
            ]),
        ];
    }
}
