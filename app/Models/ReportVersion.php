<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'version_no',
        'return_data',
        'data_hash',
        'status_at_snapshot',
        'reason',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'version_no' => 'integer',
        'return_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function indicatorValues(): HasMany
    {
        return $this->hasMany(ReportIndicatorValue::class, 'report_version_id');
    }
}
