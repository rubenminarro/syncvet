<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\ClinicLogoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * Usuario autenticado: datos, clínica, sucursal actual, roles, permisos y ficha
 * de veterinario. Espera las relaciones cargadas (AuthService::profile).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'clinic' => [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                // FASE 2, entrega 2.5 (P12): el logo se muestra después del login; su ruta nunca sale.
                'has_logo' => app(ClinicLogoService::class)->hasLogo($this->clinic),
            ],
            'current_branch' => $this->currentBranch ? [
                'id' => $this->currentBranch->id,
                'code' => $this->currentBranch->code,
                'name' => $this->currentBranch->name,
            ] : null,
            'roles' => $this->roles->pluck('name')->sort()->values()->all(),
            'permissions' => $this->permissionNames(),
            'veterinarian' => $this->veterinarian ? [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
            ] : null,
        ];
    }

    /**
     * Permisos efectivos, ordenados. El administrador pasa todo por Gate::before:
     * recibe todos los permisos, aunque su rol no los tenga asignados.
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        $names = $this->resource->hasRole('administrador')
            ? Permission::where('guard_name', 'api')->pluck('name')
            : $this->resource->getAllPermissions()->pluck('name');

        return $names->unique()->sort()->values()->all();
    }
}
