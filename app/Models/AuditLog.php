<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * Only the redas:audit:prune command may flip this flag during controlled retention pruning.
     */
    public static bool $allowPrune = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'scope_code',
        'details',
        'ip_address',
        'location',
        'user_agent',
        'request_id',
        'http_method',
        'route_name',
        'status',
        'prev_hash',
        'entry_hash',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            if (! self::$allowPrune) {
                throw new LogicException('Audit logs cannot be deleted outside controlled retention pruning.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOfEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }
}
