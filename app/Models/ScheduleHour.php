<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleHour extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'weekday',
        'starts_at',
        'ends_at',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
