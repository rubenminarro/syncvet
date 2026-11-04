<?php

use App\Enums\PatientSex;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes. El dueño puede cambiar: el paciente es una entidad propia.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('name', 100);
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained('breeds')->restrictOnDelete();
            $table->string('sex', 20)->default(PatientSex::UNKNOWN->value);
            $table->boolean('is_neutered')->default(false);
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('color', 80)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('microchip', 30)->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->text('allergies')->nullable();
            $table->text('notes')->nullable();
            // Campos personalizados definidos por la clínica.
            $table->json('custom_fields')->nullable();
            $table->timestamp('deceased_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['client_id', 'name']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
