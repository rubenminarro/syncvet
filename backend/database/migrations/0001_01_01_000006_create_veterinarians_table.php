<?php

use App\Enums\VeterinarianStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha del veterinario, vinculada a su usuario.
 * Base de la agenda, la historia clínica y los honorarios.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('veterinarians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('license_number', 30)->nullable();
            $table->string('specialty', 100)->nullable();
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->string('status', 20)->default(VeterinarianStatus::ACTIVE->value);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clinic_id', 'code']);
            $table->unique(['clinic_id', 'license_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veterinarians');
    }
};
