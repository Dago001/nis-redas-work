<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingIndicator extends Model
{
    protected $fillable = [
        'code',
        'name',
        'definition',
        'data_type',
        'unit',
        'category',
        'section',
        'validation_rules',
        'status',
    ];

    protected $casts = [
        'validation_rules' => 'array',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ReportIndicatorValue::class, 'indicator_id');
    }
}
