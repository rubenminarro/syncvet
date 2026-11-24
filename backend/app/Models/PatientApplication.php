<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Models\Concerns\BelongsToClinic;
use App\Enums\PatientApplicationType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplicación de un paciente (FASE 4, entrega 4.4; pregunta 12 del diseño, R-099): vacuna,
 * desparasitación interna o externa o medicamento, con el lote y la próxima dosis como texto y fecha;
 * con o sin consulta. La carga y la anula PatientApplicationService; no se borra: anulada, queda visible
 * y marcada (cancelled_at, cancelled_by, cancellation_reason). Se anula también con su consulta.
 *
 * Deuda 2 (revisado en la 4.4): la anulación y dose_number como entero.
 */
class PatientApplication extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'patient_id',
        'consultation_id',
        'consultation_item_id',
        'product_id',
        'type',
        'name',
        'lot_number',
        'lot_expires_at',
        'dose',
        'route',
        'applied_at',
        'veterinarian_id',
        'dose_number',
        'next_dose_due_at',
        'notes',
        'created_by',
        // Entrega 4.4: la anulación.
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => PatientApplicationType::class,
            'lot_expires_at' => 'date',
            'applied_at' => AppDateTime::class,
            'next_dose_due_at' => 'date',
            'dose_number' => 'integer',
            'cancelled_at' => AppDateTime::class,
        ];
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function consultationItem(): BelongsTo
    {
        return $this->belongsTo(ConsultationItem::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }
}
