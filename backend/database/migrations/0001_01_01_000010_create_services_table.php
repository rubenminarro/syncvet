<?php

use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\TaxRate;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de servicios (consultas, vacunaciones, cirugías, peluquería...).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('category', 30)->default(ServiceCategory::CONSULTATION->value);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->decimal('standard_price', 14, 2)->default(0);
            $table->unsignedTinyInteger('tax_rate')->default(TaxRate::TEN->value);
            $table->string('status', 20)->default(ServiceStatus::ACTIVE->value);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'category', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
