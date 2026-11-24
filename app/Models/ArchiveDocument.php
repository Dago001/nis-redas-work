<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchiveDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'doc_type',
        'description',
        'report_period',
        'category',
        'scope_code',
        'zonal_code',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'scan_status',
        'retention_until',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'retention_until' => 'date',
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
