<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'workflow_stage',
        'workflow_path',
        'category',
        'report_type',
        'report_period',
        'scope_code',
        'section_code',
        'zonal_code',
        'return_data',
        'comments',
        'supervisor_id',
        'last_action_by',
        'current_version',
        'idempotency_key',
    ];

    protected $casts = [
        'return_data' => 'array',
        'workflow_path' => 'array',
        'current_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function lastActionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_action_by');
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(ApplicationComment::class)->oldest();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->orderBy('version_no');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ReportVersion::class)->ofMany('version_no', 'max');
    }

    public function indicatorValues(): HasMany
    {
        return $this->hasMany(ReportIndicatorValue::class);
    }

    public function consolidatedSources(): HasMany
    {
        return $this->hasMany(ConsolidatedReportSource::class);
    }

    public function displayCategory(): string
    {
        $cat = strtolower((string) ($this->category ?? ''));
        $scope = strtoupper((string) ($this->scope_code ?? ''));

        if ($cat === 'zone' || $this->user?->user_category === 'zonal_officer' || str_starts_with($scope, 'ZONE-')) {
            return 'Zone';
        }

        return match ($cat) {
            'directorate' => 'Directorate',
            'cgis', 'cgis_unit' => 'CGIS Unit',
            default => 'State',
        };
    }

    /**
     * Scope submissions that are awaiting review at the given workflow stage.
     */
    public function scopeAwaiting($query, string $stage)
    {
        return $query->where('workflow_stage', $stage)
            ->whereNotIn('status', ['approved', 'rejected']);
    }
}
