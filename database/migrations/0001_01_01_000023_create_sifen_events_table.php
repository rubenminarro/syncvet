<?php

use App\Enums\SifenEventStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos SIFEN: cancelación e inutilización (emisor) y los del receptor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sifen_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('reason', 500);
            // Rango inutilizado, etc.
            $table->json('data')->nullable();
            $table->string('status', 20)->default(SifenEventStatus::PENDING->value);
            $table->string('response_code', 10)->nullable();
            $table->text('response_message')->nullable();
            $table->string('xml_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sifen_events');
    }
};
