<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DbExpr
{
    /**
     * Portable YYYY-MM SQL expression across SQLite, MySQL, and PostgreSQL.
     */
    public static function yearMonth(string $column): string
    {
        if (! preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column identifier [{$column}].");
        }

        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
