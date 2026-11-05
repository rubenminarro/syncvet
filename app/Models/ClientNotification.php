<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\ClientNotificationStatus;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientNotification extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'client_id',
        'type',
        'channel',
        'subject_type',
        'subject_id',
        'recipient',
        'template',
        'variables',
        'status',
        'external_id',
        'error',
        'attempts',
        'sent_at',
        'dedupe_key',
        'resent_from_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'variables' => 'array',
            'status' => ClientNotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function resentFrom(): BelongsTo
    {
        return $this->belongsTo(ClientNotification::class, 'resent_from_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
