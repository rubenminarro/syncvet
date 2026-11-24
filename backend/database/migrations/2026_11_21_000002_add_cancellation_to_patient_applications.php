<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La anulación de las aplicaciones (FASE 4, entrega 4.4; pregunta 12 del diseño, R-099): una
 * aplicación no se borra, se anula con motivo (y queda visible, marcada). cancelled_at es datetime
 * (hora de negocio, R-047) y lo pone PHP. Solo estructura: no se migran datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_applications', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable()->after('created_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('patient_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
