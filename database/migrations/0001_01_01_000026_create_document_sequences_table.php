<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador por serie de los códigos internos (BUD-, OT-, COM-, REP-, SRV-,
 * DIA-). Solo estructura: la fila de cada serie la crea DocumentSequenceService
 * en su primer uso, con el máximo id de la tabla + 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('key', 50);
            $table->unsignedBigInteger('next_value');
            $table->timestamps();

            $table->unique(['clinic_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
