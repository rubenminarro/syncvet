<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\ConsultationStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'code',
        'branch_id',
        'patient_id',
        'client_id',
        'appointment_id',
        'veterinarian_id',
        'consulted_at',
        'reason',
        'history',
        'weight_kg',
        'temperature_c',
        'heart_rate',
        'respiratory_rate',
        'mucous_membranes',
        'capillary_refill_time',
        'body_condition_score',
        'physical_exam',
        'diagnosis',
        'plan',
        'status',
        'created_by',
        'closed_at',
        'closed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'temperature_c' => 'decimal:2',
            'status' => ConsultationStatus::class,
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsultationItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ConsultationAttachment::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PatientApplication::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
