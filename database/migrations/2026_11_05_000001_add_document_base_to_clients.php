<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Número base del documento del cliente, único por clínica (FASE 1, entrega 1.2;
 * R-018): la cédula y el RUC de una persona ("99999903" y "99999903-6") son el
 * mismo documento. Lo completa el modelo al guardar; acá se calcula para los
 * clientes que ya existen, con el mismo criterio que App\Support\DocumentBase,
 * copiado para que la migración no cambie si esa clase cambia.
 *
 * Si dos clientes activos de una clínica comparten la base, la migración se
 * detiene con sus ids: hay que resolver el duplicado antes de crear el índice.
 */
return new class extends Migration
{
    private const DELETED_PREFIX = '//deleted_';

    public function up(): void
    {
        // Primero se calculan y se revisan las bases, sin tocar la tabla: en MySQL
        // el DDL no se deshace y un fallo a mitad dejaría la columna creada.
        $bases = [];
        $seen = [];

        DB::table('clients')->select(['id', 'clinic_id', 'document_number'])->orderBy('id')
            ->each(function (object $client) use (&$bases, &$seen) {
                $base = $this->baseOf($client->document_number);
                $key = $client->clinic_id . '|' . strtolower($base);

                if (isset($seen[$key])) {
                    throw new RuntimeException(
                        "Los clientes {$seen[$key]} y {$client->id} de la clínica {$client->clinic_id} tienen el mismo documento base ({$base})."
                    );
                }

                $seen[$key] = $client->id;
                $bases[$client->id] = $base;
            });

        Schema::table('clients', function (Blueprint $table) {
            // 50, como document_number: la baja le agrega el mismo sufijo.
            $table->string('document_base', 50)->nullable()->after('document_number');
        });

        foreach ($bases as $id => $base) {
            DB::table('clients')->where('id', $id)->update(['document_base' => $base]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('document_base', 50)->nullable(false)->change();
            $table->unique(['clinic_id', 'document_base']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['clinic_id', 'document_base']);
            $table->dropColumn('document_base');
        });
    }

    private function baseOf(string $document): string
    {
        $suffix = '';
        $position = strpos($document, self::DELETED_PREFIX);

        if ($position !== false) {
            $suffix = substr($document, $position);
            $document = substr($document, 0, $position);
        }

        if (preg_match('/^(\d+)(?:-\d)?$/', $document, $parts)) {
            $base = ltrim($parts[1], '0');

            return ($base === '' ? '0' : $base) . $suffix;
        }

        return strtoupper($document) . $suffix;
    }
};
