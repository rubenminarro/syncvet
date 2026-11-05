<?php

use App\Enums\PatientDocumentStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas y documentos emitidos (recetas, certificados, órdenes,
 * constancias). El documento emitido guarda su contenido y no cambia: solo se anula.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('name', 150);
            // HTML con variables, renderizado a PDF (resources/views/pdf).
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'type', 'name']);
        });

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->restrictOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title', 200);
            $table->longText('content');
            $table->json('data')->nullable();
            $table->string('pdf_path')->nullable();
            // Para un QR de validación pública.
            $table->uuid('verification_code')->unique();
            $table->string('status', 20)->default(PatientDocumentStatus::ISSUED->value);
            $table->foreignId('veterinarian_id')->nullable()->constrained('veterinarians')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('document_templates');
    }
};
