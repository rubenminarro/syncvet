<?php

namespace App\Services;

use App\Exceptions\BranchException;
use App\Http\Requests\BranchMessages;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CatalogName;
use App\Support\CurrentClinic;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sucursales (FASE 2, entrega 2.1). Sin baja: solo se inactivan (P9). Siempre hay
 * una por defecto en la clínica, que no se inactiva. El alta crea su depósito por
 * defecto en la misma transacción (pregunta 17).
 *
 * Concurrencia: el alta y la edición bloquean primero la fila de la clínica
 * (CatalogLock: códigos, nombres, establecimientos y la sucursal por defecto) y
 * después la sucursal (lockForUpdate). Orden de bloqueo: clínica → sucursal →
 * depósito (CLAUDE.md §3).
 */
class BranchService
{
    public const CODE_TAKEN = 'Ya hay una sucursal con ese código.';

    public const NAME_TAKEN = 'Ya hay una sucursal con ese nombre.';

    public const ESTABLISHMENT_TAKEN = 'Ya hay una sucursal con ese código de establecimiento.';

    public const DEFAULT_REQUIRED = 'Siempre tiene que haber una sucursal por defecto: marcá otra.';

    public const INACTIVE_DEFAULT = 'Una sucursal inactiva no puede ser la sucursal por defecto: activala primero.';

    private const FIELDS = ['code', 'name', 'establishment', 'address', 'phone', 'is_active'];

    public function __construct(
        protected WarehouseService $warehouseService
    ) {
    }

    /**
     * @param array{code: string, name: string, establishment: string, address?: ?string, phone?: ?string, is_default?: bool, warehouse_code: string, warehouse_name?: ?string} $data
     */
    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();

            $this->ensureUnique($data, null, [
                'warehouse_code' => $this->warehouseService->codeError($data['warehouse_code']),
            ]);

            $branch = Branch::create(Arr::only($data, ['code', 'name', 'establishment', 'address', 'phone']));

            // La primera sucursal de una clínica sin ninguna por defecto también lo es:
            // siempre hay una (clinic:create ya crea SUC-01).
            if (($data['is_default'] ?? false) || ! Branch::query()->where('is_default', true)->exists()) {
                $this->makeDefault($branch);
            }

            Warehouse::create([
                'branch_id' => $branch->id,
                'code' => $data['warehouse_code'],
                'name' => $data['warehouse_name'] ?? BranchMessages::DEFAULT_WAREHOUSE_NAME,
                'is_default' => true,
            ]);

            return $branch;
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateBranchRequest (semántica parcial)
     */
    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            CatalogLock::forCurrentClinic();
            $branch = Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureUnique($data, $branch);
            $this->ensureEstablishmentIsEditable($branch, $data);
            $this->ensureDefaultRules($branch, $data);

            $wasActive = $branch->is_active;
            $branch->fill(Arr::only($data, self::FIELDS))->save();

            if (($data['is_default'] ?? false) && ! $branch->is_default) {
                $this->makeDefault($branch);
            }

            // Pregunta 18: quienes la tenían como sucursal actual quedan sin ella hasta
            // elegir otra; la asignación (branch_user) se conserva.
            if ($wasActive && ! $branch->is_active) {
                $this->clearCurrentBranch($branch);
            }

            return $branch;
        });
    }

    /**
     * Código, nombre y establecimiento únicos en la clínica (el scope de la clínica ve
     * solo las propias, activas o no). Se informan juntos, con los errores extra del
     * alta (el código del depósito).
     *
     * @param array<string, mixed> $data
     * @param array<string, ?string> $extra
     */
    private function ensureUnique(array $data, ?Branch $ignore, array $extra = []): void
    {
        $others = Branch::query()->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()));

        $errors = [
            'code' => isset($data['code']) && (clone $others)->where('code', $data['code'])->exists() ? self::CODE_TAKEN : null,
            'name' => isset($data['name']) && CatalogName::isTaken($data['name'], (clone $others)->pluck('name')) ? self::NAME_TAKEN : null,
            'establishment' => isset($data['establishment']) && (clone $others)->where('establishment', $data['establishment'])->exists()
                ? self::ESTABLISHMENT_TAKEN
                : null,
            ...$extra,
        ];

        $errors = array_filter($errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * El establecimiento numera las facturas (SIFEN): no cambia si la sucursal ya
     * tiene alguna (P9). Reenviar el mismo valor no es un cambio.
     *
     * @param array<string, mixed> $data
     */
    private function ensureEstablishmentIsEditable(Branch $branch, array $data): void
    {
        if (! array_key_exists('establishment', $data) || $data['establishment'] === $branch->establishment) {
            return;
        }

        if ($branch->invoices()->exists()) {
            throw new BranchException(
                'El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas.',
                422,
                'BRANCH_ESTABLISHMENT_NOT_EDITABLE'
            );
        }
    }

    /**
     * La por defecto no se inactiva (409) ni deja de serlo (422: se marca otra); una
     * inactiva no pasa a ser la por defecto (422). is_default: false en otra sucursal
     * no cambia nada (el formulario manda todos los campos).
     *
     * @param array<string, mixed> $data
     */
    private function ensureDefaultRules(Branch $branch, array $data): void
    {
        $active = $data['is_active'] ?? $branch->is_active;

        if ($branch->is_default && ! $active) {
            throw new BranchException(
                'La sucursal por defecto no se puede inactivar: marcá otra como sucursal por defecto primero.',
                409,
                'BRANCH_DEFAULT_NOT_DEACTIVATABLE'
            );
        }

        if (! array_key_exists('is_default', $data)) {
            return;
        }

        if (! $data['is_default'] && $branch->is_default) {
            throw ValidationException::withMessages(['is_default' => self::DEFAULT_REQUIRED]);
        }

        if ($data['is_default'] && ! $active) {
            throw ValidationException::withMessages(['is_default' => self::INACTIVE_DEFAULT]);
        }
    }

    /**
     * La sucursal pasa a ser la por defecto y la anterior deja de serlo, una por una
     * (auditoría). La fila de la clínica ya está bloqueada.
     */
    private function makeDefault(Branch $branch): void
    {
        Branch::query()
            ->where('is_default', true)
            ->whereKeyNot($branch->getKey())
            ->get()
            ->each(fn (Branch $previous) => $previous->update(['is_default' => false]));

        $branch->is_default = true;
        $branch->save();
    }

    /**
     * User no tiene el scope de la clínica: el filtro va explícito (CLAUDE.md §3).
     * Modelo por modelo, para que quede en la auditoría.
     */
    private function clearCurrentBranch(Branch $branch): void
    {
        User::query()
            ->where('clinic_id', CurrentClinic::id())
            ->where('current_branch_id', $branch->getKey())
            ->get()
            ->each(fn (User $user) => $user->update(['current_branch_id' => null]));
    }
}
