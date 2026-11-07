<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Exceptions\UserException;
use App\Http\Requests\UserMessages;
use App\Models\Branch;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\DeletedSuffix;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios de la clínica (FASE 2, entregas 2.2a y 2.2b): alta, edición, inactivación
 * y baja, con sus roles (P3) y sucursales. Solo el administrador (P2).
 *
 * P4: nadie deja la clínica sin un administrador activo (USER_LAST_ADMIN) y el
 * administrador no se inactiva ni se da de baja a sí mismo (USER_SELF_ACTION).
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
    /**
     * Referencias a un usuario que impiden darlo de baja (P1): cada clave foránea a
     * users, salvo audit_logs (la auditoría no es uso) y branch_user (la asignación
     * se conserva). La ficha de veterinario cuenta aunque esté eliminada.
     * UserDeleteTest::test_usage_covers_every_foreign_key_to_users exige que la lista
     * cubra toda clave foránea a users.
     *
     * @var array<string, list<string>> tabla => columnas
     */
    public const USAGE = [
        'veterinarians' => ['user_id'],
        'schedule_blocks' => ['created_by'],
        'appointments' => ['created_by', 'cancelled_by'],
        'inventory_movements' => ['created_by'],
        'consultations' => ['created_by', 'closed_by', 'cancelled_by'],
        'consultation_attachments' => ['uploaded_by'],
        'patient_applications' => ['created_by'],
        'patient_documents' => ['created_by', 'cancelled_by'],
        'reminders' => ['created_by'],
        'client_notifications' => ['created_by'],
        'cash_registers' => ['user_id'],
        'cash_movements' => ['created_by', 'cancelled_by'],
        'invoices' => ['created_by', 'issued_by', 'cancelled_by'],
        'payments' => ['created_by', 'cancelled_by'],
        'sifen_events' => ['created_by'],
    ];

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
     * @param User $actor el usuario que hace el pedido
     */
    public function update(User $user, #[\SensitiveParameter] array $data, User $actor): User
    {
        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $user->is($actor)) {
            $this->failSelfAction();
        }

        return DB::transaction(function () use ($user, $data) {
            CatalogLock::forCurrentClinic();
            $user = User::query()->inClinic()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('roles', $data) || array_key_exists('is_active', $data)) {
                $this->ensureAdministratorRemains(
                    $user,
                    $data['roles'] ?? $user->getRoleNames()->all(),
                    (bool) ($data['is_active'] ?? $user->is_active)
                );
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

            // Inactivar revoca todos sus tokens (el evento updated de User); reactivar no
            // se los devuelve.
            if (array_key_exists('is_active', $data)) {
                $user->is_active = (bool) $data['is_active'];
            }

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
     * Baja de un usuario sin uso (P1): ni a sí mismo ni al último administrador activo
     * (P4). Con uso → 409 USER_IN_USE: se inactiva. Al eliminarlo, el email lleva el
     * sufijo de baja (queda libre), sus tokens se revocan (evento deleted de User) y
     * branch_user se conserva.
     *
     * Orden de bloqueo: clínica → usuario; el uso se revisa dentro de la transacción.
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            $this->failSelfAction();
        }

        DB::transaction(function () use ($user) {
            CatalogLock::forCurrentClinic();
            $user = User::query()->inClinic()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureAdministratorRemains($user, [], false);

            if ($this->isInUse($user)) {
                throw new UserException(
                    'El usuario tiene registros a su nombre y no se puede eliminar: inactivalo.',
                    409,
                    'USER_IN_USE'
                );
            }

            $user->email = $user->email . DeletedSuffix::for($user);
            $user->save();
            $user->delete();
        });
    }

    /**
     * P4: la clínica conserva al menos un administrador activo. Si el usuario es hoy un
     * administrador activo y deja de serlo (pierde el rol, se inactiva o se da de baja),
     * tiene que quedar otro administrador activo de la clínica; si no, 409. Los
     * inactivos, los eliminados y los de otra clínica no cuentan. La fila de la clínica
     * ya está bloqueada: dos pedidos simultáneos cuentan de a uno.
     *
     * @param list<string> $roles los roles que el usuario tendrá
     */
    private function ensureAdministratorRemains(User $user, array $roles, bool $active): void
    {
        $admin = UserRole::ADMINISTRATOR->value;

        $isActiveAdministrator = $user->is_active && $user->roles()->where('name', $admin)->exists();
        $staysActiveAdministrator = $active && in_array($admin, $roles, true);

        if (! $isActiveAdministrator || $staysActiveAdministrator) {
            return;
        }

        $others = User::query()->inClinic()->active()->whereKeyNot($user->getKey())->role($admin, 'api')->count();

        if ($others === 0) {
            throw new UserException(
                'La clínica tiene que tener al menos un administrador activo.',
                409,
                'USER_LAST_ADMIN'
            );
        }
    }

    /**
     * Alguna fila de USAGE lo referencia. Por el id, que es único en toda la base: no
     * hace falta la clínica. La ficha de veterinario eliminada también cuenta (DB::table
     * no aplica SoftDeletes).
     */
    private function isInUse(User $user): bool
    {
        foreach (self::USAGE as $table => $columns) {
            $query = DB::table($table)->where(function ($query) use ($columns, $user) {
                foreach ($columns as $column) {
                    $query->orWhere($column, $user->getKey());
                }
            });

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function failSelfAction(): never
    {
        throw new UserException('No podés inactivarte ni darte de baja a vos mismo.', 409, 'USER_SELF_ACTION');
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
