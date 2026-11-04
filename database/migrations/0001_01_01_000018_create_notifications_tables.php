<?php

use App\Enums\ClientNotificationStatus;
use App\Enums\ReminderStatus;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos: bandeja interna de Laravel, avisos a clientes (WhatsApp o email) y
 * recordatorios programados.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Lo que hay que avisar y cuándo (próxima vacuna, control, turno).
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->restrictOnDelete();
            $table->string('type', 30);
            // Origen: aplicación, turno, consulta.
            $table->nullableMorphs('source');
            $table->date('due_date');
            // Cuándo sale el aviso (por defecto, unos días antes de due_date).
            $table->dateTime('notify_at');
            $table->string('status', 20)->default(ReminderStatus::PENDING->value);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'notify_at']);
            $table->index(['patient_id', 'due_date']);
        });

        Schema::create('client_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('channel', 20);
            // Documento que origina el aviso (recordatorio, turno, factura, documento).
            $table->morphs('subject');
            // Destino al momento de registrar el aviso (null si el cliente no tiene).
            $table->string('recipient', 100)->nullable();
            // Plantilla aprobada por Meta y sus variables (WhatsApp Cloud API).
            $table->string('template', 100)->nullable();
            $table->json('variables')->nullable();
            $table->string('status', 20)->default(ClientNotificationStatus::PENDING->value);
            $table->string('external_id', 100)->nullable()->index();
            // Motivo de la omisión o del fallo del envío.
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            // Evita repetir un aviso automático (null en los avisos que pueden repetirse).
            $table->string('dedupe_key', 150)->nullable()->unique();
            $table->foreignId('resent_from_id')->nullable()->constrained('client_notifications')->restrictOnDelete();
            // Usuario que reenvió el aviso (null en los automáticos).
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_notifications');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('notifications');
    }
};
