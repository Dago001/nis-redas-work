<?php

namespace App\Services\Reporting;

use App\Models\Application;
use App\Models\ReportIndicatorValue;
use App\Models\ReportingIndicator;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class ReportVersioning
{
    /**
     * Create an immutable version snapshot and extract structured indicator values.
     * Call inside a database transaction.
     */
    public static function snapshot(Application $application, ?User $actor, string $reason = 'submitted'): ReportVersion
    {
        /** @var Application $locked */
        $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

        $nextVersion = max((int) $locked->current_version, (int) ReportVersion::query()->where('application_id', $locked->id)->max('version_no')) + 1;
        $returnData = is_array($locked->return_data) ? $locked->return_data : [];
        $encoded = json_encode($returnData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $version = ReportVersion::create([
            'application_id' => $locked->id,
            'version_no' => $nextVersion,
            'return_data' => $returnData,
            'data_hash' => hash('sha256', $encoded),
            'status_at_snapshot' => (string) $locked->status,
            'reason' => $reason,
            'created_by' => $actor?->id ?? $locked->user_id,
            'created_at' => now(),
        ]);

        $locked->current_version = $nextVersion;
        $locked->save();

        self::syncIndicators($locked, $version, $returnData);

        $application->current_version = $nextVersion;

        return $version;
    }

    /**
     * @param  array<string, mixed>  $returnData
     */
    private static function syncIndicators(Application $application, ReportVersion $version, array $returnData): void
    {
        if (! Schema::hasTable('reporting_indicators') || ! Schema::hasTable('report_indicator_values')) {
            return;
        }

        ReportIndicatorValue::query()
            ->where('application_id', $application->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $extracted = IndicatorExtractor::extract($returnData);

        foreach ($extracted as $code => $meta) {
            try {
                $indicator = ReportingIndicator::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $meta['name'],
                        'data_type' => $meta['type'],
                        'category' => $application->category,
                        'section' => $meta['section'],
                        'status' => 'provisional',
                    ]
                );
            } catch (QueryException) {
                $indicator = ReportingIndicator::query()->where('code', $code)->firstOrFail();
            }

            ReportIndicatorValue::create([
                'application_id' => $application->id,
                'report_version_id' => $version->id,
                'indicator_id' => $indicator->id,
                'reporting_unit_code' => $application->section_code ?: $application->scope_code,
                'category' => (string) ($application->category ?? 'state'),
                'report_period' => $application->report_period,
                'value' => $meta['value'],
                'is_current' => true,
            ]);
        }
    }
}
