<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las enmiendas de una consulta cerrada (FASE 4, entrega 4.2; pregunta 4 y D4 del diseño, R-099):
 * una cerrada no se edita, se corrige con registros aparte ligados a ella; lo escrito nunca
 * cambia. Inmutable, como audit_logs: sin updated_at ni baja (el modelo lo impide); la consulta no
 * se borra mientras tenga enmiendas (restrictOnDelete). created_at es datetime (hora de negocio,
 * R-047) y lo pone PHP, sin valor por defecto de la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('consultation_id')->constrained('consultations')->restrictOnDelete();
            // Uno de los campos clínicos de la consulta, o null para una nota general.
            $table->string('field', 40)->nullable();
            $table->text('content');
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->index(['consultation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_amendments');
    }
};
