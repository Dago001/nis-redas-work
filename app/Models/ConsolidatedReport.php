<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsolidatedReport extends Model
{
    public const LEVEL_ZONAL = 'zonal';
    public const LEVEL_DIRECTORATE = 'directorate';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'reference',
        'level',
        'scope_code',
        'report_period',
        'title',
        'status',
        'workflow_stage',
        'current_revision_no',
        'approved_revision_no',
        'is_stale',
        'stale_reason',
        'stale_flagged_at',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'returned_reason',
    ];

    protected $casts = [
        'current_revision_no' => 'integer',
        'approved_revision_no' => 'integer',
        'is_stale' => 'boolean',
        'stale_flagged_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ConsolidatedReportRevision::class)->orderByDesc('revision_no');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(ConsolidatedReportRevision::class)->ofMany('revision_no', 'max');
    }
}
