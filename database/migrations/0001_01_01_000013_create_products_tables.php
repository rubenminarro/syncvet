<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\TaxRate;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos (medicamentos, vacunas, insumos, alimentos), saldo por depósito y
 * lotes con vencimiento (aviso con 30 días de margen).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('barcode', 50)->nullable();
            $table->string('name', 200);
            $table->string('type', 20)->default(ProductType::SUPPLY->value);
            $table->string('unit', 20)->default('unit');
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->unsignedTinyInteger('tax_rate')->default(TaxRate::TEN->value);
            $table->boolean('tracks_stock')->default(true);
            $table->boolean('tracks_lots')->default(false);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->string('status', 20)->default(ProductStatus::ACTIVE->value);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['clinic_id', 'code']);
            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'type', 'status']);
            $table->index(['clinic_id', 'barcode']);
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('average_cost', 12, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('lot_number', 50);
            $table->date('expires_at')->nullable();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
            $table->index(['clinic_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_lots');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
    }
};
