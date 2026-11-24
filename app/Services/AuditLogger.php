<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;

class AuditLogger
{
    private const REDACT_PATTERN = '/pass(word)?|otp|token|secret|code_hash|authorization|api_key|mfa/i';

    /**
     * Log an immutable, redacted, hash-chained audit event.
     */
    public static function log(
        ?User $user,
        string $action,
        string $status = 'success',
        ?string $entityType = null,
        string|int|null $entityId = null,
        array $details = [],
        ?string $ip = null,
        ?string $location = null,
        ?string $userAgent = null,
        ?string $scopeCode = null
    ): AuditLog {
        $sanitizedDetails = self::redact($details);
        $resolvedIp = $ip ?? self::ip();
        $resolvedUa = $userAgent ?? self::userAgent();
        $resolvedScope = $scopeCode ?? self::resolveScope($user);
        $requestId = self::requestId();
        $httpMethod = self::httpMethod();
        $routeName = self::routeName();
        $createdAt = now();

        $entry = DB::transaction(function () use (
            $user,
            $action,
            $status,
            $entityType,
            $entityId,
            $resolvedScope,
            $sanitizedDetails,
            $resolvedIp,
            $location,
            $resolvedUa,
            $requestId,
            $httpMethod,
            $routeName,
            $createdAt
        ): AuditLog {
            $latestQuery = AuditLog::query()->orderByDesc('id');
            if (DB::getDriverName() === 'mysql') {
                $latestQuery->lockForUpdate();
            }
            $prevHash = (string) ($latestQuery->value('entry_hash') ?? str_repeat('0', 64));

            $entryHash = self::computeHash(
                prevHash: $prevHash,
                userId: $user?->id,
                action: $action,
                entityType: $entityType,
                entityId: $entityId !== null ? (string) $entityId : null,
                details: $sanitizedDetails,
                createdAt: $createdAt->format('Y-m-d H:i:s')
            );

            return AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'scope_code' => $resolvedScope,
                'details' => $sanitizedDetails,
                'ip_address' => $resolvedIp,
                'location' => $location,
                'user_agent' => $resolvedUa,
                'request_id' => $requestId,
                'http_method' => $httpMethod,
                'route_name' => $routeName,
                'status' => $status,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
                'created_at' => $createdAt,
            ]);
        });

        try {
            $channel = (string) config('audit.channel', 'audit');
            Log::channel($channel)->info($action, [
                'audit_id' => $entry->id,
                'user_id' => $entry->user_id,
                'status' => $status,
                'entity_type' => $entityType,
                'entity_id' => $entry->entity_id,
                'scope_code' => $resolvedScope,
                'request_id' => $requestId,
                'ip_address' => $resolvedIp,
                'entry_hash' => $entry->entry_hash,
            ]);
        } catch (\Throwable) {
            // Never fail a business transaction if file logging is temporarily unwritable.
        }

        return $entry;
    }

    public static function computeHash(
        string $prevHash,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?string $entityId,
        array $details,
        string $createdAt
    ): string {
        $payload = implode('|', [
            $prevHash,
            (string) ($userId ?? ''),
            $action,
            (string) ($entityType ?? ''),
            (string) ($entityId ?? ''),
            json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            $createdAt,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Recursively redact sensitive keys so passwords, OTPs, and tokens never enter audit logs.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::REDACT_PATTERN, $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redact($value);
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    public static function logAuthAttempt(
        ?User $user,
        string $status,
        array $details = [],
        ?string $ip = null,
        ?string $location = null
    ): AuditLog {
        return self::log(
            user: $user,
            action: 'auth.attempt',
            status: $status,
            entityType: 'user',
            entityId: $user?->id,
            details: $details,
            ip: $ip,
            location: $location
        );
    }

    public static function logAuthBlocked(
        ?User $user,
        string $reason,
        array $details = [],
        ?string $ip = null,
        ?string $location = null
    ): AuditLog {
        return self::log(
            user: $user,
            action: 'auth.blocked',
            status: 'blocked',
            entityType: 'user',
            entityId: $user?->id,
            details: array_merge(['reason' => $reason], $details),
            ip: $ip,
            location: $location
        );
    }

    public static function logLogout(User $user, ?string $ip = null): AuditLog
    {
        return self::log(
            user: $user,
            action: 'auth.logout',
            status: 'success',
            entityType: 'user',
            entityId: $user->id,
            ip: $ip
        );
    }

    public static function logCrud(
        User $user,
        string $action,
        string $entityType,
        string|int $entityId,
        array $details = []
    ): AuditLog {
        return self::log(
            user: $user,
            action: $action,
            status: 'success',
            entityType: $entityType,
            entityId: $entityId,
            details: $details
        );
    }

    public static function logSettingChange(
        User $user,
        string $key,
        mixed $oldValue,
        mixed $newValue
    ): AuditLog {
        return self::log(
            user: $user,
            action: 'settings.update',
            status: 'success',
            entityType: 'setting',
            entityId: $key,
            details: [
                'key' => $key,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );
    }

    private static function resolveScope(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return SubmissionWorkflow::scopeCodeForApprover($user)
            ?? SubmissionWorkflow::scopeCodeForUser($user)
            ?? $user->primary_location_code;
    }

    private static function ip(): ?string
    {
        try {
            return RequestFacade::instance()?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        } catch (\Throwable) {
            return $_SERVER['REMOTE_ADDR'] ?? null;
        }
    }

    private static function userAgent(): ?string
    {
        try {
            return RequestFacade::instance()?->userAgent() ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        } catch (\Throwable) {
            return $_SERVER['HTTP_USER_AGENT'] ?? null;
        }
    }

    private static function requestId(): ?string
    {
        try {
            $req = RequestFacade::instance();
            if (! $req) {
                return null;
            }
            $existing = $req->attributes->get('redas_request_id') ?: $req->header('X-Request-Id');
            if (is_string($existing) && trim($existing) !== '') {
                $clean = substr(trim($existing), 0, 64);
                $req->attributes->set('redas_request_id', $clean);

                return $clean;
            }
            $generated = (string) Str::uuid();
            $req->attributes->set('redas_request_id', $generated);

            return $generated;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function httpMethod(): ?string
    {
        try {
            return RequestFacade::instance()?->method();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function routeName(): ?string
    {
        try {
            return RequestFacade::instance()?->route()?->getName();
        } catch (\Throwable) {
            return null;
        }
    }
}
