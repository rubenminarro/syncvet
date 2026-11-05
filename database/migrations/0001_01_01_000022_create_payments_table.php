<?php

use App\Enums\PaymentStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de facturas (total o parcial), registrados en la caja abierta.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            // Recibo numerado: solo en pagos de facturas a crédito.
            $table->unsignedInteger('receipt_sequence')->nullable();
            $table->string('receipt_number', 15)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('cash_change', 14, 2)->default(0);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('bank', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default(PaymentStatus::REGISTERED->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'receipt_sequence']);
            $table->unique(['clinic_id', 'receipt_number']);
            $table->index(['invoice_id', 'status']);
            $table->index(['clinic_id', 'payment_date', 'method']);
            $table->index(['cash_register_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
