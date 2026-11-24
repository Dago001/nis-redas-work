<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidatedReportSource extends Model
{
    protected $fillable = [
        'revision_id',
        'application_id',
        'report_version_id',
        'included',
        'exclusion_reason',
    ];

    protected $casts = [
        'included' => 'boolean',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ConsolidatedReportRevision::class, 'revision_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'report_version_id');
    }
}
