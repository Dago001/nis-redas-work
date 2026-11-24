<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'idempotency_key',
        'user_id',
        'channel',
        'template_key',
        'status',
        'attempts',
        'provider',
        'provider_message_id',
        'provider_response',
        'last_error',
        'accepted_at',
        'failed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'provider_response' => 'array',
        'accepted_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
