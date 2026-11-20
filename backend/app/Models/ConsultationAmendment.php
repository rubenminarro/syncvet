<?php

namespace App\Models;

use App\Exceptions\ConsultationException;
use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enmienda de una consulta cerrada (FASE 4, entrega 4.2; pregunta 4 del diseño, R-099): el campo
 * clínico que corrige (o null, una nota general), el contenido, el motivo, quién y cuándo. La
 * crea ConsultationService::amend. Inmutable, como AuditLog: no se edita ni se borra (409
 * CONSULTATION_AMENDMENT_IMMUTABLE); sin updated_at.
 */
class ConsultationAmendment extends Model
{
    use BelongsToClinic, Auditable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'consultation_id',
        'field',
        'content',
        'reason',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new ConsultationException(
            'Las enmiendas no pueden modificarse.',
            409,
            'CONSULTATION_AMENDMENT_IMMUTABLE'
        ));

        static::deleting(fn () => throw new ConsultationException(
            'Las enmiendas no pueden eliminarse.',
            409,
            'CONSULTATION_AMENDMENT_IMMUTABLE'
        ));
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
