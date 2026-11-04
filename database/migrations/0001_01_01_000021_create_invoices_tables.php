<?php

use App\Enums\InvoiceCondition;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SifenStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas con SIFEN. El número y el CDC se
 * asignan al emitir; una factura emitida es inmutable y se corrige con nota de crédito.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->restrictOnDelete();
            $table->string('status', 20)->default(InvoiceStatus::DRAFT->value);
            // Numeración: se asigna al emitir.
            $table->foreignId('invoice_stamp_id')->nullable()->constrained('invoice_stamps')->restrictOnDelete();
            $table->string('stamp_number', 8)->nullable();
            $table->string('number', 15)->nullable()->unique();
            // Datos del cliente copiados (editables en borrador).
            $table->string('customer_name', 150);
            $table->string('customer_tax_id', 30)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('condition', 10)->default(InvoiceCondition::CASH->value);
            $table->date('due_date')->nullable();
            $table->decimal('total_exempt', 14, 2)->default(0);
            $table->decimal('total_5', 14, 2)->default(0);
            $table->decimal('total_10', 14, 2)->default(0);
            $table->decimal('iva_5', 14, 2)->default(0);
            $table->decimal('iva_10', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('payment_status', 20)->default(InvoicePaymentStatus::PENDING->value);
            $table->decimal('balance', 14, 2)->default(0);
            // SIFEN.
            $table->string('cdc', 44)->nullable()->unique();
            $table->string('security_code', 9)->nullable();
            $table->string('sifen_status', 30)->default(SifenStatus::NOT_SENT->value);
            $table->string('sifen_response_code', 10)->nullable();
            $table->text('sifen_response_message')->nullable();
            $table->string('sifen_batch_id', 30)->nullable();
            $table->string('xml_path')->nullable();
            // KuDE: representación impresa (PDF).
            $table->string('kude_path')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('sifen_sent_at')->nullable();
            $table->timestamp('sifen_approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('issue_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'payment_status']);
            $table->index(['sifen_status', 'sifen_sent_at']);
            $table->index('issue_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            // Origen de la línea: ítem de consulta, servicio o producto vendido sin consulta.
            $table->morphs('source');
            $table->string('code', 50)->nullable();
            $table->string('description', 255);
            // cUniMed de SIFEN (77 = unidad).
            $table->unsignedSmallInteger('unit_of_measure')->default(77);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->unsignedTinyInteger('tax_rate');
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
