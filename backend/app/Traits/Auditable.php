<?php

namespace App\Traits;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Registra en audit_logs el alta, la modificación, la baja y (con
 * SoftDeletes) la restauración del modelo. No cubre las actualizaciones
 * masivas por query builder, que no disparan eventos de modelo.
 *
 * El modelo puede definir $auditExclude (campos que no se registran) y reemplazar
 * valores con auditValues() (los que no deben quedar, como una ruta interna).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditLogger::class)->modelEvent(AuditEvent::CREATED, $model));
        static::updated(fn ($model) => app(AuditLogger::class)->modelEvent(AuditEvent::UPDATED, $model));
        static::deleted(fn ($model) => app(AuditLogger::class)->modelEvent(AuditEvent::DELETED, $model));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => app(AuditLogger::class)->modelEvent(AuditEvent::RESTORED, $model));
        }
    }

    /**
     * Tipo corto con el que se guarda y se filtra (p. ej. "patient_application").
     */
    public function auditType(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * @return string[]
     */
    public function auditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }

    /**
     * Datos que identifican el registro cuando no tiene clave propia.
     */
    public function auditContext(): array
    {
        return [];
    }

    /**
     * Los valores antes y después, ya con el contexto, tal como se guardan: el modelo
     * puede reemplazar los que no deben quedar en la auditoría.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        return $values;
    }
}
