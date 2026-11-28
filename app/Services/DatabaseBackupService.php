<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;

class DatabaseBackupService
{
    public static function backupDir(): string
    {
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * Gather live database connection stats for the System Settings overview.
     *
     * @return array<string, mixed>
     */
    public static function databaseInfo(): array
    {
        $connection = (string) config('database.default', 'sqlite');
        $driver = (string) config("database.connections.{$connection}.driver", $connection);
        $dbName = (string) config("database.connections.{$connection}.database", '');
        $sizeBytes = 0;
        $tableCount = 0;

        try {
            if ($driver === 'sqlite' && $dbName !== '' && $dbName !== ':memory:' && is_file($dbName)) {
                $sizeBytes = (int) filesize($dbName);
                $tableCount = count(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $tables = DB::select('SHOW TABLE STATUS');
                $tableCount = count($tables);
                foreach ($tables as $t) {
                    $sizeBytes += (int) ($t->Data_length ?? 0) + (int) ($t->Index_length ?? 0);
                }
            } elseif ($driver === 'pgsql') {
                $tableCount = count(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));
                $row = DB::selectOne('SELECT pg_database_size(current_database()) AS sz');
                $sizeBytes = (int) ($row->sz ?? 0);
            }
        } catch (\Throwable) {
            // Keep safe defaults
        }

        return [
            'connection' => $connection,
            'driver' => strtoupper($driver),
            'database' => $driver === 'sqlite' ? basename($dbName ?: 'memory') : $dbName,
            'table_count' => $tableCount,
            'size_human' => self::formatBytes($sizeBytes),
        ];
    }

    /**
     * Create a complete database backup and return its metadata entry.
     *
     * @return array<string, mixed>
     */
    public static function createBackup(User $actor): array
    {
        $dir = self::backupDir();
        $connection = (string) config('database.default', 'sqlite');
        $driver = (string) config("database.connections.{$connection}.driver", $connection);
        $stamp = now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            $sourcePath = (string) config("database.connections.{$connection}.database", '');
            if ($sourcePath !== '' && $sourcePath !== ':memory:' && is_file($sourcePath)) {
                $filename = "nis-redas-backup-sqlite-{$stamp}.sqlite";
                $targetPath = $dir.DIRECTORY_SEPARATOR.$filename;
                if (! @copy($sourcePath, $targetPath)) {
                    throw new \RuntimeException('Failed to copy SQLite database file.');
                }
            } else {
                // In-memory SQLite (e.g. during automated tests): dump full SQL schema + rows
                $filename = "nis-redas-backup-sqlite-{$stamp}.sql";
                $targetPath = $dir.DIRECTORY_SEPARATOR.$filename;
                file_put_contents($targetPath, self::buildSqliteSqlDump());
            }
        } else {
            $filename = "nis-redas-backup-{$driver}-{$stamp}.sql";
            $targetPath = $dir.DIRECTORY_SEPARATOR.$filename;
            file_put_contents($targetPath, self::buildGenericSqlDump($driver));
        }

        $size = is_file($targetPath) ? (int) filesize($targetPath) : 0;
        $entry = [
            'filename' => $filename,
            'driver' => strtoupper($driver),
            'size_bytes' => $size,
            'size_human' => self::formatBytes($size),
            'created_by' => $actor->name.' ('.$actor->service_number.')',
            'created_at' => now()->toDateTimeString(),
        ];

        $manifest = self::readManifest();
        $manifest[$filename] = $entry;
        self::writeManifest($manifest);

        return $entry;
    }

    /**
     * List all existing database backup files (newest first).
     *
     * @return list<array<string, mixed>>
     */
    public static function listBackups(): array
    {
        $dir = self::backupDir();
        $manifest = self::readManifest();
        $items = [];

        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if ($name === 'manifest.json' || ! preg_match('/\.(sqlite|sql|zip|gz)$/i', $name)) {
                continue;
            }

            $meta = $manifest[$name] ?? [];
            $size = (int) $file->getSize();
            $items[] = [
                'filename' => $name,
                'driver' => $meta['driver'] ?? strtoupper($file->getExtension()),
                'size_bytes' => $size,
                'size_human' => self::formatBytes($size),
                'created_by' => $meta['created_by'] ?? 'System Administrator',
                'created_at' => $meta['created_at'] ?? date('Y-m-d H:i:s', $file->getMTime()),
                'mtime' => $file->getMTime(),
            ];
        }

        usort($items, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return $items;
    }

    /**
     * Resolve a safe absolute path to a backup file, preventing path traversal.
     */
    public static function safePath(string $filename): ?string
    {
        $base = basename($filename);
        if ($base === '' || $base !== $filename || $base === 'manifest.json' || ! preg_match('/^[A-Za-z0-9._\-]+\.(sqlite|sql|zip|gz)$/', $base)) {
            return null;
        }

        $dir = realpath(self::backupDir());
        $full = realpath(self::backupDir().DIRECTORY_SEPARATOR.$base);

        if ($dir === false || $full === false || ! str_starts_with($full, $dir)) {
            return null;
        }

        return is_file($full) ? $full : null;
    }

    public static function deleteBackup(string $filename): bool
    {
        $path = self::safePath($filename);
        if ($path === null) {
            return false;
        }

        $deleted = @unlink($path);
        if ($deleted) {
            $manifest = self::readManifest();
            unset($manifest[basename($filename)]);
            self::writeManifest($manifest);
        }

        return $deleted;
    }

    private static function buildSqliteSqlDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $out = ["-- NIS-REDAS Full Database Backup (SQLite)", '-- Generated: '.now()->toDateTimeString(), 'BEGIN TRANSACTION;', ''];

        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $t) {
            $out[] = "DROP TABLE IF EXISTS \"{$t->name}\";";
            $out[] = $t->sql.';';
            foreach (DB::table($t->name)->cursor() as $row) {
                $arr = (array) $row;
                $cols = implode(', ', array_map(static fn ($c) => '"'.$c.'"', array_keys($arr)));
                $vals = implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($arr)));
                $out[] = "INSERT INTO \"{$t->name}\" ({$cols}) VALUES ({$vals});";
            }
            $out[] = '';
        }

        $out[] = 'COMMIT;';

        return implode("\n", $out)."\n";
    }

    private static function buildGenericSqlDump(string $driver): string
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $out = ["-- NIS-REDAS Full Database Backup ({$driver})", '-- Generated: '.now()->toDateTimeString(), ''];

        $tableNames = [];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $out[] = 'SET FOREIGN_KEY_CHECKS=0;';
            foreach (DB::select('SHOW TABLES') as $row) {
                $tableNames[] = (string) array_values((array) $row)[0];
            }
        } elseif ($driver === 'pgsql') {
            foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename") as $row) {
                $tableNames[] = (string) $row->tablename;
            }
        }

        foreach ($tableNames as $table) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $createRow = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
                $createSql = $createRow['Create Table'] ?? '';
                if ($createSql !== '') {
                    $out[] = "DROP TABLE IF EXISTS `{$table}`;";
                    $out[] = $createSql.';';
                }
            }

            foreach (DB::table($table)->cursor() as $row) {
                $arr = (array) $row;
                $q = in_array($driver, ['mysql', 'mariadb'], true) ? '`' : '"';
                $cols = implode(', ', array_map(static fn ($c) => $q.$c.$q, array_keys($arr)));
                $vals = implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($arr)));
                $out[] = "INSERT INTO {$q}{$table}{$q} ({$cols}) VALUES ({$vals});";
            }
            $out[] = '';
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $out[] = 'SET FOREIGN_KEY_CHECKS=1;';
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function readManifest(): array
    {
        $path = self::backupDir().DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $manifest
     */
    private static function writeManifest(array $manifest): void
    {
        $path = self::backupDir().DIRECTORY_SEPARATOR.'manifest.json';
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        return round($val, 2).' '.$units[$i];
    }
}
