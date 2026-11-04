<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vacunas, antiparasitarios y medicamentos aplicados. Alimenta las constancias
 * y los recordatorios. Copia el lote para que la constancia no cambie.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->restrictOnDelete();
            $table->foreignId('consultation_item_id')->nullable()->constrained('consultation_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('name', 200);
            $table->string('lot_number', 50)->nullable();
            $table->date('lot_expires_at')->nullable();
            $table->string('dose', 50)->nullable();
            $table->string('route', 30)->nullable();
            $table->dateTime('applied_at');
            $table->foreignId('veterinarian_id')->nullable()->constrained('veterinarians')->restrictOnDelete();
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->date('next_dose_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'type', 'applied_at']);
            $table->index('next_dose_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_applications');
    }
};
