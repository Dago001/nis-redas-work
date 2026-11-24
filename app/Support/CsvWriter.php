<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvWriter
{
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Sanitize a CSV cell value to prevent spreadsheet formula injection (CWE-1236).
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $str = str_replace("\0", '', (string) $value);
        if ($str === '') {
            return '';
        }

        $firstChar = $str[0];
        if (in_array($firstChar, self::FORMULA_TRIGGERS, true)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public static function writeRow($handle, array $row): void
    {
        $sanitized = array_map(static fn (mixed $cell): string => self::sanitizeCell($cell), $row);
        fputcsv($handle, $sanitized);
    }

    /**
     * Stream a CSV download with UTF-8 BOM and formula-injection protection.
     *
     * @param  callable(resource): void  $writerCallback
     */
    public static function stream(string $filename, callable $writerCallback): StreamedResponse
    {
        return response()->streamDownload(function () use ($writerCallback): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");
            $writerCallback($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
