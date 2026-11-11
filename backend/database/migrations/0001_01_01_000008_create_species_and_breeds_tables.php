<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de especies y razas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->id();
            // Nulo: especie global (la siembra SpeciesSeeder); si no, propia de una clínica.
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Con clinic_id nulo el índice no impide repetir un nombre global: lo valida la aplicación.
            $table->unique(['clinic_id', 'name']);
        });

        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            // Nulo: raza global; si no, propia de una clínica.
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->restrictOnDelete();
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'species_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('breeds');
        Schema::dropIfExists('species');
    }
};
