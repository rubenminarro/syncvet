<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Exceptions\AuditException;
use App\Models\Scopes\ClinicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría. Inmutable: no se modifica ni se elimina.
 */
class AuditLog extends Model
{
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
            'created_at' => 'datetime',
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
