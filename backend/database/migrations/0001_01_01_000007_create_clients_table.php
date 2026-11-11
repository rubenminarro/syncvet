<?php

use App\Enums\ClientKind;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes (tutores de las mascotas): persona o empresa.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('kind', 20)->default(ClientKind::PERSON->value);
            $table->string('document_number', 30);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_name', 150)->nullable();
            $table->string('phone');
            $table->string('email');
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id', 'business_name']);
            $table->index(['clinic_id', 'phone']);
            $table->unique(['clinic_id', 'document_number']);
            $table->unique(['clinic_id', 'email']);
            $table->index(['clinic_id', 'last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
