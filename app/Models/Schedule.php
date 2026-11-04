<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'veterinarian_id',
        'service_id',
        'slot_minutes',
        'color',
        'online_booking',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'online_booking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(ScheduleHour::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
