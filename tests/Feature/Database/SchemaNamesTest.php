<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Nombres de índices y claves foráneas de hasta 64 caracteres (CLAUDE.md §3), el
 * límite de MySQL. SQLite, la base de los tests, no tiene límite: un nombre largo
 * pasaría la suite y fallaría recién al migrar en MySQL.
 *
 * - Índices: en las dos bases.
 * - Claves foráneas: solo en MySQL. SQLite no guarda su nombre, y calcular el
 *   automático daría falsos positivos justo donde la migración le puso uno propio.
 */
class SchemaNamesTest extends TestCase
{
    private const MAX_LENGTH = 64;

    public function test_index_names_fit_in_mysql(): void
    {
        $long = [];
        $checked = 0;

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                // Los automáticos de SQLite (sqlite_autoindex_…) no salen de las migraciones.
                if (str_starts_with($index['name'], 'sqlite_autoindex_')) {
                    continue;
                }

                $checked++;

                if (strlen($index['name']) > self::MAX_LENGTH) {
                    $long[] = "{$table}: {$index['name']} (" . strlen($index['name']) . ')';
                }
            }
        }

        $this->assertSame([], $long, 'Índices con nombres de más de 64 caracteres: darles uno propio.');
        $this->assertGreaterThan(50, $checked, 'No se encontraron índices.');
    }

    public function test_foreign_key_names_fit_in_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('SQLite no guarda el nombre de las claves foráneas: se verifica en MySQL.');
        }

        $names = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->pluck('CONSTRAINT_NAME');

        $this->assertNotEmpty($names);
        $this->assertSame([], $names->filter(fn (string $name) => strlen($name) > self::MAX_LENGTH)->values()->all());
    }
}
