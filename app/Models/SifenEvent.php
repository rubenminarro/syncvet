<?php

namespace App\Models;

use App\Enums\SifenEventStatus;
use App\Enums\SifenEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SifenEvent extends Model
{
    protected $fillable = [
        'invoice_id',
        'type',
        'reason',
        'data',
        'status',
        'response_code',
        'response_message',
        'xml_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => SifenEventType::class,
            'data' => 'array',
            'status' => SifenEventStatus::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
