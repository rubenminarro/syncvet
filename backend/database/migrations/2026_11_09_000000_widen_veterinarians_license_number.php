<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La matrícula del veterinario pasa de 30 a 60 caracteres (FASE 2, entrega 2.3;
 * pregunta 29 del diseño): 30 de la matrícula más el sufijo de baja entero
 * (//deleted_<id>_<timestamp>: 21 caracteres más el id). La validación sigue en 30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinarians', function (Blueprint $table) {
            $table->string('license_number', 60)->nullable()->change();
        });
    }

    /**
     * No se corta la matrícula para volver atrás: si alguna (en general, la de un
     * veterinario dado de baja, con su sufijo) pasa de 30 caracteres, se detiene con
     * un mensaje claro en lugar del error de la base.
     */
    public function down(): void
    {
        // En PHP: LENGTH cuenta bytes en MySQL y caracteres en SQLite.
        $longer = DB::table('veterinarians')
            ->whereNotNull('license_number')
            ->pluck('license_number', 'id')
            ->filter(fn (string $license) => mb_strlen($license) > 30)
            ->keys();

        if ($longer->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede volver la matrícula de los veterinarios a 30 caracteres: '
                . $longer->count() . ' veterinario(s) la tienen más larga (ids ' . $longer->implode(', ') . '). '
                . 'Corregilas antes de revertir esta migración.'
            );
        }

        Schema::table('veterinarians', function (Blueprint $table) {
            $table->string('license_number', 30)->nullable()->change();
        });
    }
};
