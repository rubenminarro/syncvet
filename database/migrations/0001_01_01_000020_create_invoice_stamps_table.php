<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timbrados (Paraguay) con el tipo de documento SIFEN.
 * Numeración 001-001-0000001 = establecimiento - punto de expedición - correlativo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            // iTiDE de SIFEN: 1 factura, 5 nota de crédito, 6 nota de débito.
            $table->unsignedTinyInteger('document_type')->default(1);
            $table->string('stamp_number', 8);
            $table->string('establishment', 3);
            $table->string('expedition_point', 3);
            $table->date('valid_from');
            // El timbrado electrónico no tiene fin de vigencia.
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['stamp_number', 'establishment', 'expedition_point', 'document_type'],
                'inv_stamps_number_est_exp_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_stamps');
    }
};
