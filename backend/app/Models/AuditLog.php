<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Enums\AuditEvent;
use App\Exceptions\AuditException;
use App\Models\Scopes\ClinicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría. Inmutable: no se modifica ni se elimina.
 *
 * auditable_type es el tipo corto del modelo (Auditable::auditType(): "patient",
 * "system_setting"), no la clase: no hay relación morphTo. La consulta por la API
 * (FASE 2, entrega 2.6) filtra por los tipos de AUDITABLE_TYPES.
 */
class AuditLog extends Model
{
    /**
     * Los tipos de los modelos con Auditable: la lista cerrada del filtro auditable_type
     * (FASE 2, entrega 2.6). AuditableTypesTest exige que coincida con los modelos.
     */
    public const AUDITABLE_TYPES = [
        'appointment',
        'branch',
        'breed',
        'cash_register',
        'client',
        'clinic',
        'consultation',
        'consultation_item',
        'document_template',
        'invoice',
        'invoice_stamp',
        'patient',
        'patient_application',
        'patient_document',
        'payment',
        'product',
        'schedule',
        'schedule_block',
        'schedule_hour',
        'service',
        'species',
        'system_setting',
        'user',
        'veterinarian',
        'warehouse',
    ];

    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'event',
        'auditable_type',
        'auditable_id',
        'user_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => AppDateTime::class,
        ];
    }

    protected static function booted(): void
    {
        // Se lee solo la clínica en contexto. Al crear, AuditLogger completa clinic_id
        // de forma explícita (puede ser nulo: login fallido con un email desconocido).
        static::addGlobalScope(new ClinicScope());

        static::updating(fn () => throw new AuditException(
            'Los registros de auditoría no pueden modificarse.',
            409,
            'AUDIT_LOG_IMMUTABLE'
        ));

        static::deleting(fn () => throw new AuditException(
            'Los registros de auditoría no pueden eliminarse.',
            409,
            'AUDIT_LOG_IMMUTABLE'
        ));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
