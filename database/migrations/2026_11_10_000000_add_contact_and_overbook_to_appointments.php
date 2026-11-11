<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos (FASE 3, entrega 3.2a): los datos de contacto de un turno sin cliente ni paciente
 * (P4), la marca de sobreturno (P2) y un índice para la cola del día y la superposición del
 * mismo veterinario entre sus agendas (pregunta 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('contact_name', 150)->nullable()->after('veterinarian_id');
            $table->string('contact_phone', 20)->nullable()->after('contact_name');
            $table->boolean('is_overbooked')->default(false)->after('source');

            $table->index(['veterinarian_id', 'starts_at'], 'appointments_veterinarian_id_starts_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_veterinarian_id_starts_at_index');
            $table->dropColumn(['contact_name', 'contact_phone', 'is_overbooked']);
        });
    }
};
