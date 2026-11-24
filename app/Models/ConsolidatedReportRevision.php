<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidatedReportRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'consolidated_report_id',
        'revision_no',
        'narrative',
        'aggregated_data',
        'gaps',
        'created_by',
        'change_note',
        'created_at',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'aggregated_data' => 'array',
        'gaps' => 'array',
        'created_at' => 'datetime',
    ];

    public function consolidatedReport(): BelongsTo
    {
        return $this->belongsTo(ConsolidatedReport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ConsolidatedReportSource::class, 'revision_id');
    }
}
