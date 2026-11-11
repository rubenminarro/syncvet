<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estados del turno (FASE 3, entrega 3.2b; pregunta 20): la hora en que se marcó atendido y la
 * hora en que se marcó ausente, para la cola del día, la demora y el ausentismo. Columnas
 * datetime (hora de negocio, R-047): las llena PHP con AppDateTime, sin valor por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('attended_at')->nullable()->after('checked_in_at');
            $table->dateTime('no_show_at')->nullable()->after('attended_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['attended_at', 'no_show_at']);
        });
    }
};
