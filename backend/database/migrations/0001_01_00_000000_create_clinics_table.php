<?php

use App\Enums\ClinicStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clínicas (D1, registro R-002): una base compartida con clinic_id en las tablas
 * de negocio. Ordena antes que users, que la referencia. Sin SoftDeletes: una
 * clínica se suspende, no se elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('legal_name', 150);
            // RUC sin el dígito verificador; único en todo el sistema (R-005).
            $table->string('ruc', 20)->unique();
            $table->string('ruc_dv', 1);
            $table->string('timezone', 40)->default('America/Asuncion');
            $table->string('status', 20)->default(ClinicStatus::ACTIVE->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
