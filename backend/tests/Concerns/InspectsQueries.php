<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orden de las consultas dentro de la transacción de un Service (FASE 2): los tests de
 * bloqueo (UserLockTest, VeterinarianLockTest) verifican que una fila se bloquea antes
 * que otra o antes de una lectura. El "for update" solo lo escribe MySQL.
 */
trait InspectsQueries
{
    /**
     * @return list<string>
     */
    protected function queriesOf(callable $request): array
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
    protected function lockOf(string $table): callable
    {
        return fn (string $sql) => preg_match('/^select \* from [`"]' . $table . '[`"] where .*[`"]' . $table . '[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));
    }

    /**
     * Cualquier lectura de la tabla (sin bloqueo).
     */
    protected function readOf(string $table): callable
    {
        return fn (string $sql) => str_contains($sql, 'from "' . $table . '"') || str_contains($sql, 'from `' . $table . '`');
    }

    /**
     * @param list<string> $queries
     */
    protected function assertBefore(array $queries, callable $first, callable $then, string $message): void
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
    protected function firstIndex(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $sql) {
            if ($matches($sql)) {
                return $index;
            }
        }

        return null;
    }
}
