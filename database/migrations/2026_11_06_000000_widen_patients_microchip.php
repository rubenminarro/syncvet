<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El microchip del paciente pasa de 30 a 60 caracteres (FASE 1, entrega 1.3;
 * pregunta 15 del diseño): 30 del chip más el sufijo de baja entero
 * (//deleted_<id>_<timestamp>: 21 caracteres más el id). Con 30, el sufijo se
 * cortaba y dos bajas del mismo chip podían chocar con el índice único. La
 * validación sigue en 30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('microchip', 60)->nullable()->change();
        });
    }

    /**
     * Falla en MySQL si algún paciente dado de baja tiene más de 30 caracteres:
     * no se corta el microchip para volver atrás.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('microchip', 30)->nullable()->change();
        });
    }
};
