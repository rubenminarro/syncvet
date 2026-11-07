<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario en los listados (FASE 2, entrega 2.2a). Nunca password ni remember_token.
 * Los roles y las sucursales van cargados (with) para no consultar por fila.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => RoleResource::collection(
                $this->roles->pluck('name')->sort()->values()
                    ->map(fn (string $name) => UserRole::tryFrom($name))->filter()->values()
            ),
            'branches' => $this->branches->sortBy('code')->values()->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
            ]),
            'current_branch_id' => $this->current_branch_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
