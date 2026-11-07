<?php

namespace Tests\Feature\Branches;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Concurrencia de sucursales y depósitos (FASE 2, entrega 2.1): el alta y la edición
 * bloquean la fila de la clínica (CatalogLock) antes de leer los códigos y nombres y
 * de mover la por defecto, y después la sucursal. Orden: clínica → sucursal →
 * depósito. Como CatalogLockTest, se verifica el orden de las consultas dentro de la
 * transacción del Service; el "for update" solo lo escribe MySQL.
 */
class BranchLockTest extends TestCase
{
    use InteractsWithBranches;

    public function test_creating_a_branch_locks_the_clinic_before_reading_the_branches(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/branches', $this->branchPayload(['is_default' => true]))->assertCreated());

        $this->assertBefore($queries, $this->clinicLock(), $this->readOf('branches'), 'La clínica se bloqueó después de leer las sucursales.');
    }

    public function test_updating_a_branch_locks_the_clinic_and_then_the_branch(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $this->actAsBranchManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/branches/{$branch->id}", ['name' => 'Sucursal Norte', 'is_default' => true])->assertOk());

        $this->assertBefore($queries, $this->clinicLock(), $this->branchLock(), 'La sucursal se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->branchLock(), $this->readOf('branches', 'name'), 'Se leyeron los nombres antes de bloquear la sucursal.');
    }

    /**
     * Inactivar una sucursal vacía la sucursal actual de sus usuarios (pregunta 18):
     * clínica → sucursal → usuario (desvío 8 de la implementación, aceptado).
     */
    public function test_deactivating_a_branch_locks_clinic_branch_and_then_the_users(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $branch = $this->branchIn($clinic);
        $user = User::factory()->for($clinic)->create(['current_branch_id' => $branch->id]);
        $this->actAsBranchManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/branches/{$branch->id}", ['is_active' => false])->assertOk());

        $this->assertNull($user->fresh()->current_branch_id);
        $this->assertBefore($queries, $this->clinicLock(), $this->branchLock(), 'La sucursal se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->branchLock(), $this->usersQuery(), 'Se tocaron los usuarios antes de bloquear la sucursal.');
    }

    public function test_creating_and_updating_a_warehouse_lock_clinic_branch_and_warehouse_in_order(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsBranchManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson("/api/branches/{$branch->id}/warehouses", ['code' => 'DEP-05', 'name' => 'Farmacia'])->assertCreated());
        $this->assertBefore($queries, $this->clinicLock(), $this->branchLock(), 'La sucursal se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->branchLock(), $this->readOf('warehouses'), 'Se leyeron los depósitos antes de bloquear la sucursal.');

        $id = DB::table('warehouses')->where('code', 'DEP-05')->value('id');
        $queries = $this->queriesOf(fn () => $this->patchJson("/api/warehouses/{$id}", ['is_default' => true])->assertOk());
        $this->assertBefore($queries, $this->clinicLock(), $this->branchLock(), 'La sucursal se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->branchLock(), $this->lockOf('warehouses'), 'El depósito se bloqueó antes que la sucursal.');
    }

    /**
     * @return list<string>
     */
    private function queriesOf(callable $request): array
    {
        // Solo las consultas dentro de la transacción del Service: el middleware también
        // lee la clínica, pero fuera de ella y sin bloquearla.
        $base = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries, $base) {
            if ($query->connection->transactionLevel() > $base) {
                $queries[] = Str::lower($query->sql);
            }
        });

        $request();

        return $queries;
    }

    private function clinicLock(): callable
    {
        return $this->lockOf('clinics');
    }

    private function branchLock(): callable
    {
        return $this->lockOf('branches');
    }

    /**
     * La lectura de una fila por su id; en MySQL, con "for update".
     */
    private function lockOf(string $table): callable
    {
        return fn (string $sql) => preg_match('/^select \* from [`"]' . $table . '[`"] where [`"]' . $table . '[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));
    }

    /**
     * La primera lectura o escritura de usuarios (los que tienen la sucursal como actual).
     */
    private function usersQuery(): callable
    {
        return fn (string $sql) => preg_match('/^(select .+ from|update) [`"]users[`"]/', $sql) === 1;
    }

    /**
     * Cualquier lectura de la tabla (los únicos, la por defecto), o la de una columna.
     */
    private function readOf(string $table, ?string $column = null): callable
    {
        $what = $column === null ? '.+' : '[`"]' . $column . '[`"]';

        return fn (string $sql) => preg_match('/^select ' . $what . ' from [`"]' . $table . '[`"]/', $sql) === 1 && ! str_ends_with($sql, 'for update');
    }

    /**
     * @param list<string> $queries
     */
    private function assertBefore(array $queries, callable $first, callable $then, string $message): void
    {
        $a = $this->firstIndex($queries, $first);
        $b = $this->firstIndex($queries, $then);

        $this->assertNotNull($a, "No se encontró la primera consulta:\n" . implode("\n", $queries));
        $this->assertNotNull($b, "No se encontró la segunda consulta:\n" . implode("\n", $queries));
        $this->assertLessThan($b, $a, $message . "\n" . implode("\n", $queries));
    }

    /**
     * @param list<string> $queries
     */
    private function firstIndex(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $sql) {
            if ($matches($sql)) {
                return $index;
            }
        }

        return null;
    }
}
