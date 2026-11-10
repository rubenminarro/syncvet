<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBlock extends Model
{
    use BelongsToClinic;

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
