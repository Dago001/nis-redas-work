<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'report_type',
        'format',
        'filters',
        'status',
        'stored_path',
        'row_count',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'row_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
