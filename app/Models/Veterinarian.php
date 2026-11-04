<?php

namespace App\Models;

use App\Enums\VeterinarianStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Veterinarian extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'license_number',
        'specialty',
        'commission_percentage',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VeterinarianStatus::class,
            'commission_percentage' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
