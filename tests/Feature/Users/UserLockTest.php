<?php

namespace Tests\Feature\Users;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * La lectura de una fila por su id; en MySQL, con "for update".
     */
    private function lockOf(string $table): callable
    {
        return fn (string $sql) => preg_match('/^select \* from [`"]' . $table . '[`"] where .*[`"]' . $table . '[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));
    }

    /**
     * El conteo de los otros administradores de la clínica (usuarios con un rol).
     */
    private function administratorCount(): callable
    {
        return fn (string $sql) => preg_match('/^select count\(\*\) as [`"]aggregate[`"] from [`"]users[`"]/', $sql) === 1 && str_contains($sql, 'roles');
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
