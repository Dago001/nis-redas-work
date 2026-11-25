<?php

namespace App\Services\Reporting;

class IndicatorExtractor
{
    private const SKIP_KEYS = [
        'command_name',
        'reporting_officer',
        'report_period',
        'period',
        'return_type',
        'attachments',
        'supporting_documents',
        'directorate_slug',
        'cgis_unit_slug',
        '_token',
        '_method',
        '_idempotency_key',
        'data_consent',
        'file_metadata',
    ];

    private const MAX_ABS_VALUE = 1e15;

    /**
     * Extract structured numeric indicators from nested return_data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{value:float,section:string,name:string,type:string}>
     */
    public static function extract(array $data): array
    {
        $indicators = [];
        self::walk($data, [], $indicators);

        return $indicators;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $path
     * @param  array<string, array{value:float,section:string,name:string,type:string}>  $indicators
     */
    private static function walk(array $node, array $path, array &$indicators): void
    {
        foreach ($node as $key => $value) {
            $segment = strtolower(trim((string) $key));
            if ($segment === '' || ($path === [] && in_array($segment, self::SKIP_KEYS, true))) {
                continue;
            }

            if (in_array($segment, self::SKIP_KEYS, true)) {
                continue;
            }

            $nextPath = [...$path, $segment];

            if (is_array($value)) {
                self::walk($value, $nextPath, $indicators);
                continue;
            }

            $parsed = self::parseNumericLeaf($value);
            if ($parsed === null) {
                continue;
            }

            $rawCode = implode('.', $nextPath);
            $code = self::normalizeCode($rawCode);
            $section = $nextPath[0] ?? 'general';

            $indicators[$code] = [
                'value' => $parsed['value'],
                'section' => substr($section, 0, 100),
                'name' => self::humanizeName($nextPath),
                'type' => $parsed['type'],
            ];
        }
    }

    /**
     * @return array{value:float,type:string}|null
     */
    private static function parseNumericLeaf(mixed $value): ?array
    {
        if (is_bool($value) || $value === null) {
            return null;
        }

        if (is_int($value)) {
            if (abs($value) >= self::MAX_ABS_VALUE) {
                return null;
            }

            return ['value' => (float) $value, 'type' => 'integer'];
        }

        if (is_float($value)) {
            if (! is_finite($value) || abs($value) >= self::MAX_ABS_VALUE) {
                return null;
            }

            return [
                'value' => $value,
                'type' => floor($value) == $value ? 'integer' : 'decimal',
            ];
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            // Exclude leading-zero strings (e.g. phone numbers "08012345678") while accepting "0", "0.5", "1500"
            if (! preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $trimmed)) {
                return null;
            }

            $float = (float) $trimmed;
            if (! is_finite($float) || abs($float) >= self::MAX_ABS_VALUE) {
                return null;
            }

            return [
                'value' => $float,
                'type' => str_contains($trimmed, '.') ? 'decimal' : 'integer',
            ];
        }

        return null;
    }

    private static function normalizeCode(string $rawCode): string
    {
        if (strlen($rawCode) <= 191) {
            return $rawCode;
        }

        return substr($rawCode, 0, 150).'.'.sha1($rawCode);
    }

    /**
     * @param  list<string>  $segments
     */
    private static function humanizeName(array $segments): string
    {
        $parts = array_map(
            static fn (string $seg): string => ucwords(str_replace(['_', '-'], ' ', $seg)),
            $segments
        );

        return substr(implode(' - ', $parts), 0, 191);
    }
}
