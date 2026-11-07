<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Exceptions\UserException;
use App\Http\Requests\UserMessages;
use App\Models\Branch;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios de la clínica (FASE 2, entrega 2.2a): alta y edición con sus roles (P3) y
 * sucursales. Solo el administrador (P2).
 *
 * Concurrencia: el alta y la edición bloquean primero la fila de la clínica
 * (CatalogLock) y después, al editar, el usuario. Así se ordenan con la
 * inactivación de una sucursal (clínica → sucursal → usuario, R-030), y dos
 * ediciones simultáneas no dejan la clínica sin administrador (P4). Orden de
 * bloqueo: clínica → sucursal → usuario (CLAUDE.md §3).
 *
 * User no tiene el scope de la clínica: toda consulta filtra por la clínica en
 * contexto (inClinic()), salvo el email, que es único en toda la base (R-029).
 */
class UserService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array{first_name: string, last_name: string, email: string, password: string, roles: list<string>, branch_ids: list<int>} $data
     */
    public function create(#[\SensitiveParameter] array $data): User
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();

            $branchIds = $this->availableBranchIds($data['branch_ids']);
            $email = User::normalizeEmail($data['email']);
            $this->ensureEmailIsFree($email);

            $user = new User([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $this->fullName($data['first_name'], $data['last_name']),
                'email' => $email,
                'password' => $data['password'],
                'current_branch_id' => $this->currentBranchFor($branchIds),
            ]);
            $user->clinic_id = CurrentClinic::id();
            $this->save($user);

            $this->syncBranches($user, $branchIds);
            $this->syncRoles($user, $data['roles']);

            return $user;
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateUserRequest (semántica parcial)
     */
    public function update(User $user, #[\SensitiveParameter] array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            CatalogLock::forCurrentClinic();
            $user = User::query()->inClinic()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('roles', $data)) {
                $this->ensureAdministratorRemains($user, $data['roles']);
            }

            if (array_key_exists('email', $data)) {
                $data['email'] = User::normalizeEmail($data['email']);

                if ($data['email'] !== $user->email) {
                    $this->ensureEmailIsFree($data['email']);
                }
            }

            $branchIds = null;

            if (array_key_exists('branch_ids', $data)) {
                $branchIds = $this->availableBranchIds($data['branch_ids'], $user->branches()->pluck('branches.id')->all());

                // Sin sucursal actual, o si la actual sale de la lista, se elige otra vez con la
                // regla del alta.
                if ($user->current_branch_id === null || ! in_array((int) $user->current_branch_id, $branchIds, true)) {
                    $user->current_branch_id = $this->currentBranchFor($branchIds);
                }
            }

            $user->fill(Arr::only($data, ['first_name', 'last_name', 'email', 'password']));

            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = $this->fullName($user->first_name, $user->last_name);
            }

            $this->save($user);

            // Pregunta 20: el cambio de contraseña por el administrador cierra las sesiones
            // del usuario.
            if (array_key_exists('password', $data)) {
                $user->tokens()->delete();
            }

            if ($branchIds !== null) {
                $this->syncBranches($user, $branchIds);
            }

            if (array_key_exists('roles', $data)) {
                $this->syncRoles($user, $data['roles']);
            }

            return $user;
        });
    }

    /**
     * P4: quitarle el rol administrador al último administrador de la clínica → 409.
     * La fila de la clínica ya está bloqueada: dos pedidos simultáneos cuentan de a uno.
     *
     * @param list<string> $roles
     */
    private function ensureAdministratorRemains(User $user, array $roles): void
    {
        $admin = UserRole::ADMINISTRATOR->value;

        if (in_array($admin, $roles, true) || ! $user->roles()->where('name', $admin)->exists()) {
            return;
        }

        $others = User::query()->inClinic()->whereKeyNot($user->getKey())->role($admin, 'api')->count();

        if ($others === 0) {
            throw new UserException(
                'La clínica tiene que tener al menos un administrador activo.',
                409,
                'USER_LAST_ADMIN'
            );
        }
    }

    /**
     * El email es el login: único en toda la base, también entre usuarios eliminados
     * (R-029). Consulta global a propósito (User no tiene el scope de la clínica).
     */
    private function ensureEmailIsFree(string $email): void
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => UserMessages::EMAIL_TAKEN]);
        }
    }

    /**
     * Guarda el usuario. Dos altas simultáneas del mismo email en clínicas distintas
     * no se ordenan con la fila de la clínica: el índice único frena a la segunda, que
     * recibe el mismo 422 en lugar de un 500.
     */
    private function save(User $user): void
    {
        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => UserMessages::EMAIL_TAKEN]);
        }
    }

    /**
     * Las sucursales del pedido, leídas otra vez con la clínica bloqueada: una
     * inactivada después de la validación responde 422. Se acepta una inactiva que el
     * usuario ya tiene ($kept).
     *
     * @param list<int> $ids
     * @param list<int> $kept
     * @return list<int>
     */
    private function availableBranchIds(array $ids, array $kept = []): array
    {
        $ids = array_map('intval', $ids);

        $found = Branch::query()
            ->whereKey($ids)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereKey($kept))
            ->count();

        if ($found !== count($ids)) {
            throw ValidationException::withMessages(['branch_ids' => UserMessages::BRANCH_NOT_AVAILABLE]);
        }

        return $ids;
    }

    /**
     * Sucursal actual: la por defecto si está en la lista; si no, la primera activa
     * de la lista; sin ninguna activa, ninguna.
     *
     * @param list<int> $branchIds
     */
    private function currentBranchFor(array $branchIds): ?int
    {
        $active = Branch::query()->whereKey($branchIds)->where('is_active', true)->get(['id', 'is_default']);

        $default = $active->firstWhere('is_default', true);

        if ($default !== null) {
            return $default->id;
        }

        foreach ($branchIds as $id) {
            if ($active->contains('id', $id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * branch_user, con el cambio en la auditoría (el pivote no tiene modelo propio).
     *
     * @param list<int> $branchIds
     */
    private function syncBranches(User $user, array $branchIds): void
    {
        $old = $user->branches()->pluck('branches.id')->sort()->values()->all();
        $user->branches()->sync($branchIds);
        $new = collect($branchIds)->sort()->values()->all();

        if ($old !== $new) {
            $this->auditLogger->record(AuditEvent::UPDATED, $user, ['branch_ids' => $old], ['branch_ids' => $new]);
        }
    }

    /**
     * Roles con syncRoles; el cambio queda en la auditoría como ROLES_SYNCED.
     *
     * @param list<string> $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $old = $user->getRoleNames()->sort()->values()->all();
        $user->syncRoles($roles);
        $new = $user->getRoleNames()->sort()->values()->all();

        if ($old !== $new) {
            $this->auditLogger->record(AuditEvent::ROLES_SYNCED, $user, ['roles' => $old], ['roles' => $new]);
        }
    }

    private function fullName(string $firstName, string $lastName): string
    {
        return trim("{$firstName} {$lastName}");
    }
}
