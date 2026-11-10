<?php

namespace App\Services;

use App\Exceptions\BranchException;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Support\CatalogName;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Depósitos de una sucursal (FASE 2, entrega 2.1). Sin baja: solo se inactivan. Uno
 * por defecto en cada sucursal, con las reglas de la sucursal por defecto. El código
 * es único en la clínica; el nombre, dentro de su sucursal.
 *
 * Concurrencia: la fila de la clínica (CatalogLock: el código es único en la clínica),
 * después la sucursal (el depósito por defecto) y por último el depósito. Orden de
 * bloqueo: clínica → sucursal → depósito (CLAUDE.md §3).
 */
class WarehouseService
{
    public const CODE_TAKEN = 'Ya hay un depósito con ese código.';

    public const NAME_TAKEN = 'Ya hay un depósito con ese nombre en la sucursal.';

    public const DEFAULT_REQUIRED = 'Siempre tiene que haber un depósito por defecto en la sucursal: marcá otro.';

    public const INACTIVE_DEFAULT = 'Un depósito inactivo no puede ser el depósito por defecto: activalo primero.';

    public const BRANCH_INACTIVE = 'La sucursal está inactiva.';

    /**
     * @param array{code: string, name: string, is_default?: bool} $data
     */
    public function create(Branch $branch, array $data): Warehouse
    {
        return DB::transaction(function () use ($branch, $data) {
            CatalogLock::forCurrentClinic();
            $branch = $this->lockBranch($branch->getKey());

            // Una sucursal inactiva no suma depósitos. Se revisa con la sucursal ya
            // bloqueada, para ver una inactivación simultánea.
            if (! $branch->is_active) {
                throw ValidationException::withMessages(['branch' => self::BRANCH_INACTIVE]);
            }

            $this->ensureUnique($branch, $data);

            $warehouse = Warehouse::create([
                'branch_id' => $branch->id,
                'code' => $data['code'],
                'name' => $data['name'],
            ]);

            if (($data['is_default'] ?? false) || ! $branch->warehouses()->where('is_default', true)->exists()) {
                $this->makeDefault($warehouse);
            }

            return $warehouse;
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateWarehouseRequest (semántica parcial)
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data) {
            CatalogLock::forCurrentClinic();
            $branch = $this->lockBranch($warehouse->branch_id);
            $warehouse = Warehouse::query()->whereKey($warehouse->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureUnique($branch, $data, $warehouse);
            $this->ensureDefaultRules($warehouse, $data);

            $warehouse->fill(Arr::only($data, ['code', 'name', 'is_active']))->save();

            if (($data['is_default'] ?? false) && ! $warehouse->is_default) {
                $this->makeDefault($warehouse);
            }

            return $warehouse;
        });
    }

    /**
     * El mensaje si el código ya lo usa otro depósito de la clínica (activo o no); si
     * no, null. Lo usa también el alta de una sucursal, para su depósito por defecto.
     */
    public function codeError(string $code, ?Warehouse $ignore = null): ?string
    {
        $taken = Warehouse::query()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->where('code', $code)
            ->exists();

        return $taken ? self::CODE_TAKEN : null;
    }

    private function lockBranch(int $branchId): Branch
    {
        return Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureUnique(Branch $branch, array $data, ?Warehouse $ignore = null): void
    {
        $names = $branch->warehouses()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('name');

        $errors = array_filter([
            'code' => isset($data['code']) ? $this->codeError($data['code'], $ignore) : null,
            'name' => isset($data['name']) && CatalogName::isTaken($data['name'], $names) ? self::NAME_TAKEN : null,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Las reglas de la sucursal por defecto (BranchService), dentro de la sucursal.
     *
     * @param array<string, mixed> $data
     */
    private function ensureDefaultRules(Warehouse $warehouse, array $data): void
    {
        $active = $data['is_active'] ?? $warehouse->is_active;

        if ($warehouse->is_default && ! $active) {
            throw new BranchException(
                'El depósito por defecto no se puede inactivar: marcá otro como depósito por defecto primero.',
                409,
                'WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE'
            );
        }

        if (! array_key_exists('is_default', $data)) {
            return;
        }

        if (! $data['is_default'] && $warehouse->is_default) {
            throw ValidationException::withMessages(['is_default' => self::DEFAULT_REQUIRED]);
        }

        if ($data['is_default'] && ! $active) {
            throw ValidationException::withMessages(['is_default' => self::INACTIVE_DEFAULT]);
        }
    }

    /**
     * El depósito pasa a ser el por defecto de su sucursal y el anterior deja de serlo,
     * uno por uno (auditoría). La sucursal ya está bloqueada.
     */
    private function makeDefault(Warehouse $warehouse): void
    {
        Warehouse::query()
            ->where('branch_id', $warehouse->branch_id)
            ->where('is_default', true)
            ->whereKeyNot($warehouse->getKey())
            ->get()
            ->each(fn (Warehouse $previous) => $previous->update(['is_default' => false]));

        $warehouse->is_default = true;
        $warehouse->save();
    }
}
