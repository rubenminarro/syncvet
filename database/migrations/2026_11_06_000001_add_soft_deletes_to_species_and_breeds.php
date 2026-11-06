<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baja de especies y razas (FASE 1, entrega 1.4): SoftDeletes, solo para las que
 * nunca se usaron. La baja agrega al nombre el sufijo entero
 * (//deleted_<id>_<timestamp>: 21 caracteres más el id) para liberarlo en el
 * índice único: el nombre se valida hasta 60 (especie) y 80 (raza), y la columna
 * pasa a 90 y 110 para el sufijo con ids de hasta 9 dígitos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->string('name', 90)->change();
            $table->softDeletes();
        });

        Schema::table('breeds', function (Blueprint $table) {
            $table->string('name', 110)->change();
            $table->softDeletes();
        });
    }

    /**
     * Falla en MySQL si algún nombre dado de baja pasa de los largos anteriores: no
     * se corta el nombre para volver atrás.
     */
    public function down(): void
    {
        Schema::table('breeds', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->string('name', 100)->change();
        });

        Schema::table('species', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->string('name', 80)->change();
        });
    }
};
