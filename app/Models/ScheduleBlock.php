<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de una agenda (FASE 3, entrega 3.1; pregunta 23: auditable): un rango de
 * instantes (AppDateTime) en que la agenda no recibe turnos. No se edita: se elimina y se
 * crea otro. created_by lo fija ScheduleBlockService.
 */
class ScheduleBlock extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use BelongsToClinic, Auditable, HasFactory;

    protected $fillable = [
        'schedule_id',
        'starts_at',
        'ends_at',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => AppDateTime::class,
            'ends_at' => AppDateTime::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
