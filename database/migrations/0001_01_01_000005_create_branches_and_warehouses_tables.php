<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursales y depósitos. Cada sucursal tiene su agenda, caja y stock; el
 * establecimiento SIFEN (dEst) es el de la sucursal.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            // Código de establecimiento SIFEN (dEst), ej. "001".
            $table->string('establishment', 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
            $table->unique(['clinic_id', 'establishment']);
        });

        // La sucursal y el depósito por defecto de cada clínica los crea ClinicService::create (R-004).

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['branch_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Sucursal en la que trabaja el usuario en este momento.
            $table->foreignId('current_branch_id')->nullable()->after('email')->constrained('branches')->nullOnDelete();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_branch_id');
        });

        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};
