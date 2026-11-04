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
            $table->string('kind', 20)->default(ClientKind::PERSON->value);
            $table->string('document_number', 30)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_name', 150)->nullable();
            $table->string('phone')->index();
            $table->string('email')->unique();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('business_name');
            $table->index(['last_name', 'first_name']);
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
