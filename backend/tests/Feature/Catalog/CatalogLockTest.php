<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * Concurrencia del nombre único (FASE 1, entrega 1.4): el alta y el cambio de
 * nombre bloquean la fila de la clínica (lockForUpdate) antes de leer los nombres
 * del catálogo, dentro de la misma transacción. Así dos altas simultáneas del
 * mismo nombre se ordenan y la segunda ve la primera (orden de bloqueo: clínica →
 * catálogo). Dos pedidos simultáneos no se pueden dar en un test de PHPUnit (una
 * sola conexión, dentro de la transacción de RefreshDatabase): se verifica el
 * orden de las consultas. El "for update" solo lo escribe MySQL; SQLite bloquea
 * la base entera al escribir.
 */
class CatalogLockTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_creating_a_species_locks_the_clinic_before_reading_names(): void
    {
        $this->actAsVet($this->createClinic());

        $queries = $this->queriesOf(fn () => $this->createSpecies('Hurón'));

        $this->assertLockBefore($queries, 'species');
    }

    public function test_renaming_a_species_locks_the_clinic_before_reading_names(): void
    {
        $this->actAsVet($this->createClinic());
        $id = $this->createSpecies('Hurón');

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/species/{$id}", ['name' => 'Erizo'])->assertOk());

        $this->assertLockBefore($queries, 'species');
    }

    public function test_creating_and_renaming_a_breed_lock_the_clinic_before_reading_names(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();

        $queries = $this->queriesOf(fn () => $this->createBreed($canine, 'Fila'));
        $this->assertLockBefore($queries, 'breeds');

        $id = DB::table('breeds')->where('name', 'Fila')->value('id');
        $queries = $this->queriesOf(fn () => $this->patchJson("/api/species/{$canine}/breeds/{$id}", ['name' => 'Fila Paraguayo'])->assertOk());
        $this->assertLockBefore($queries, 'breeds');
    }

    /**
     * @return list<string>
     */
    private function queriesOf(callable $request): array
    {
        // Solo las consultas dentro de una transacción propia del pedido (la del Service):
        // el middleware también lee la clínica, pero fuera de ella y sin bloquearla.
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
     * @param list<string> $queries
     */
    private function assertLockBefore(array $queries, string $table): void
    {
        $lock = $this->firstIndex($queries, fn (string $sql) => preg_match('/^select .* from [`"]clinics[`"] where [`"]clinics[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update')));
        // La lectura de los nombres: el pluck de "name" de la tabla del catálogo.
        $names = $this->firstIndex($queries, fn (string $sql) => preg_match('/^select [`"]name[`"] from [`"]' . $table . '[`"]/', $sql) === 1);

        $this->assertNotNull($lock, "No se bloqueó la clínica:\n" . implode("\n", $queries));
        $this->assertNotNull($names, "No se leyeron los nombres de {$table}:\n" . implode("\n", $queries));
        $this->assertLessThan($names, $lock, 'La clínica se bloqueó después de leer los nombres.');
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
