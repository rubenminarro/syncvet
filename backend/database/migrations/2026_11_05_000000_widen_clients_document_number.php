<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El documento del cliente pasa de 30 a 50 caracteres (FASE 1, entrega 1.2;
 * pregunta 15 del diseño): 20 del documento más el sufijo de baja entero
 * (//deleted_<id>_<timestamp>: 21 caracteres más el id). Con 30, el sufijo de un
 * documento largo se cortaba y la segunda baja del mismo documento chocaba con el
 * índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('document_number', 50)->change();
        });
    }

    /**
     * Falla en MySQL si algún cliente dado de baja tiene más de 30 caracteres: no
     * se corta el documento para volver atrás.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('document_number', 30)->change();
        });
    }
};
