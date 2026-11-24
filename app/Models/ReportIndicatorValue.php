<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportIndicatorValue extends Model
{
    protected $fillable = [
        'application_id',
        'report_version_id',
        'indicator_id',
        'reporting_unit_code',
        'category',
        'report_period',
        'value',
        'is_current',
    ];

    protected $casts = [
        'value' => 'float',
        'is_current' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'report_version_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ReportingIndicator::class, 'indicator_id');
    }
}
