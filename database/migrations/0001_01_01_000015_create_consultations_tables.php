<?php

use App\Enums\ConsultationItemBillingStatus;
use App\Enums\ConsultationStatus;
use App\Enums\TaxRate;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historia clínica: la consulta (ficha de atención), sus adjuntos y lo que se
 * usó o se cobra en ella. Cadena: turno → consulta → ítems → stock + factura + recordatorio.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            // Dueño al momento de la consulta (el paciente puede cambiar de dueño).
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('veterinarian_id')->constrained('veterinarians')->restrictOnDelete();
            $table->dateTime('consulted_at');
            $table->string('reason', 255)->nullable();
            $table->text('history')->nullable();
            // Examen físico.
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->string('mucous_membranes', 50)->nullable();
            $table->string('capillary_refill_time', 20)->nullable();
            $table->unsignedTinyInteger('body_condition_score')->nullable();
            $table->text('physical_exam')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('plan')->nullable();
            $table->string('status', 20)->default(ConsultationStatus::OPEN->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'consulted_at']);
            $table->index(['branch_id', 'consulted_at']);
            $table->index(['veterinarian_id', 'consulted_at']);
            $table->index('status');
        });

        Schema::create('consultation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('consultation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('product_lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();
            // Base de los honorarios.
            $table->foreignId('veterinarian_id')->nullable()->constrained('veterinarians')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->unsignedTinyInteger('tax_rate')->default(TaxRate::TEN->value);
            $table->decimal('total', 14, 2);
            $table->string('billing_status', 20)->default(ConsultationItemBillingStatus::PENDING->value);
            $table->timestamps();

            $table->index(['consultation_id', 'billing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_items');
        Schema::dropIfExists('consultation_attachments');
        Schema::dropIfExists('consultations');
    }
};
