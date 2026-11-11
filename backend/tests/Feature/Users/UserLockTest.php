<?php

namespace Tests\Feature\Users;

use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Concurrencia de usuarios (FASE 2, entrega 2.2a). La edición bloquea la fila de la
 * clínica antes de contar los administradores (P4) y después el usuario; el alta,
 * la clínica antes de leer las sucursales. Orden: clínica → usuario. Como
 * BranchLockTest, se verifica el orden de las consultas dentro de la transacción del
 * Service; el "for update" solo lo escribe MySQL.
 */
class UserLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithUsers;

    public function test_removing_the_administrator_role_locks_the_clinic_and_the_user_before_counting_the_administrators(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador']);
        $this->actAsUserManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->administratorCount(), 'Se contaron los administradores antes de bloquear el usuario.');
    }

    /**
     * La baja (2.2b): la clínica, después el usuario, y recién entonces el conteo de los
     * administradores activos y el uso.
     */
    public function test_deleting_a_user_locks_the_clinic_and_the_user_before_counting_and_checking_the_usage(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador']);
        $this->actAsUserManager($clinic);

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/users/{$admin->id}")->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->administratorCount(), 'Se contaron los administradores antes de bloquear el usuario.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->readOf('veterinarians'), 'Se revisó el uso antes de bloquear el usuario.');
    }

    /**
     * Inactivar (2.2b) cuenta los administradores con la clínica y el usuario bloqueados.
     */
    public function test_deactivating_an_administrator_locks_the_clinic_and_the_user_before_counting(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador']);
        $this->actAsUserManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->administratorCount(), 'Se contaron los administradores antes de bloquear el usuario.');
    }

    /**
     * La sucursal actual (me/current-branch, 2.2b): la clínica, después la sucursal y
     * después el usuario, como la inactivación de una sucursal.
     */
    public function test_choosing_the_current_branch_locks_the_clinic_then_the_branch_then_the_user(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $user = $this->userWithRoles($clinic, ['veterinario'], [$default, $north], ['current_branch_id' => $default->id]);
        $this->actingAs($user, 'sanctum');

        $queries = $this->queriesOf(fn () => $this->patchJson('/api/me/current-branch', ['branch_id' => $north->id])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('branches'), 'La sucursal se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('branches'), $this->lockOf('users'), 'El usuario se bloqueó antes que la sucursal.');
    }

    public function test_creating_a_user_locks_the_clinic_before_reading_the_branches(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/users', $this->userPayload([$branch->id]))->assertCreated());

        $this->assertBefore(
            $queries,
            $this->lockOf('clinics'),
            fn (string $sql) => preg_match('/^select count\(\*\) as [`"]aggregate[`"] from [`"]branches[`"]/', $sql) === 1,
            'Se leyeron las sucursales antes de bloquear la clínica.'
        );
    }

    /**
     * El conteo de los otros administradores de la clínica (usuarios con un rol).
     */
    private function administratorCount(): callable
    {
        return fn (string $sql) => preg_match('/^select count\(\*\) as [`"]aggregate[`"] from [`"]users[`"]/', $sql) === 1 && str_contains($sql, 'roles');
    }
}
