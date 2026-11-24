<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La consulta de la FASE 4 (entrega 4.1; C3, C7 y preguntas 2 y 6 del diseño, R-099):
 *
 * - closed_at y cancelled_at pasan de timestamp a datetime (hora de negocio, R-047; deuda 17).
 * - plan pasa a treatment (el tratamiento, texto clínico) y se suma instructions (las indicaciones
 *   para el tutor).
 * - El índice único de appointment_id (un turno, como máximo una consulta) toma un nombre propio,
 *   UNIQUE_APPOINTMENT, para reconocer su violación (409 CONSULTATION_ALREADY_EXISTS, §3). Primero
 *   se crea el nuevo y después se quita el viejo: en MySQL la clave foránea necesita un índice.
 *
 * Solo estructura: las tablas están vacías en producción (no hay producción) y no se migran datos.
 */
return new class extends Migration
{
    public const UNIQUE_APPOINTMENT = 'consultations_appointment_unique';

    private const OLD_UNIQUE_APPOINTMENT = 'consultations_appointment_id_unique';

    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->unique('appointment_id', self::UNIQUE_APPOINTMENT);
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropUnique(self::OLD_UNIQUE_APPOINTMENT);
            $table->renameColumn('plan', 'treatment');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('treatment');
            $table->dateTime('closed_at')->nullable()->change();
            $table->dateTime('cancelled_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->change();
            $table->timestamp('cancelled_at')->nullable()->change();
            $table->dropColumn('instructions');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->renameColumn('treatment', 'plan');
            $table->unique('appointment_id', self::OLD_UNIQUE_APPOINTMENT);
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_APPOINTMENT);
        });
    }
};
