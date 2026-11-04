<?php

namespace App\Models;

use App\Enums\PatientApplicationType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientApplication extends Model
{
    use Auditable;

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
    ];

    protected function casts(): array
    {
        return [
            'type' => PatientApplicationType::class,
            'lot_expires_at' => 'date',
            'applied_at' => 'datetime',
            'next_dose_due_at' => 'date',
        ];
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
