<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-layer Web Application Firewall (WAF) shield.
 *
 * Inspects request URIs, query strings, and non-password input scalars for
 * high-confidence attack signatures:
 * - Null-byte injection (\0 / %00)
 * - Path traversal (../, ..\, /etc/passwd, win.ini)
 * - SQL injection probes (UNION SELECT, SLEEP(), BENCHMARK(), WAITFOR DELAY,
 *   INFORMATION_SCHEMA, INTO OUTFILE, boolean tautologies in query/filter params)
 * - Reflected/stored script injection (<script, javascript:, onerror=/onload= handlers)
 *
 * Designed to avoid false positives on legitimate Nigerian immigration reports
 * (e.g., apostrophes in names like "O'Connor", dashes, percentages, or email templates).
 */
class WafShieldMiddleware
{
    /** @var array<string, string> High-confidence signatures checked on URI & query string */
    private const URI_QUERY_PATTERNS = [
        'null_byte' => '/(?:\x00|%00)/i',
        'path_traversal' => '/(?:\.\.[\/\\\\]|%2e%2e(?:%2f|%5c|\/|\\\\)|\/etc\/passwd|\/etc\/shadow|boot\.ini|win\.ini)/i',
        'sqli_union' => '/\bunion\s+(?:all\s+)?select\b/i',
        'sqli_time_based' => '/\b(?:sleep|benchmark|pg_sleep)\s*\(|\bwaitfor\s+delay\b/i',
        'sqli_schema_probe' => '/\b(?:information_schema|mysql\.user|sqlite_master|load_file|into\s+(?:out|dump)file)\b/i',
        'sqli_boolean_tautology' => '/(?:\'|")\s*(?:or|and)\s+(?:\d+\s*=\s*\d+|\'[^\']*\'=\'[^\']*\'|"[^"]*"="[^"]*")/i',
        'sqli_stacked_query' => '/;\s*(?:drop|truncate|alter|delete|insert|update|exec|execute)\s+(?:table|database|from|into)/i',
        'xss_script' => '/<\s*script\b|javascript\s*:|on(?:error|load|mouseover|focus)\s*=/i',
    ];

    /** @var array<string, string> Signatures checked on POST/PUT/PATCH body fields (excluding secrets & admin templates) */
    private const BODY_PATTERNS = [
        'null_byte' => '/\x00/',
        'sqli_union' => '/\bunion\s+(?:all\s+)?select\b/i',
        'sqli_time_based' => '/\b(?:sleep|benchmark|pg_sleep)\s*\(|\bwaitfor\s+delay\b/i',
        'sqli_schema_probe' => '/\b(?:information_schema|mysql\.user|sqlite_master|load_file|into\s+(?:out|dump)file)\b/i',
        'sqli_stacked_query' => '/;\s*(?:drop|truncate|alter)\s+(?:table|database)\b/i',
        'xss_script' => '/<\s*script\b[^>]*>|<\s*iframe\b[^>]*>|javascript\s*:\s*[^\s]/i',
    ];

    /** Fields skipped from body pattern checks (passwords may contain arbitrary chars; email templates store admin HTML). */
    private const SKIP_BODY_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        '_token',
        '_method',
        'body',
        'html_body',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $rawUri = (string) $request->server('REQUEST_URI', $request->getRequestUri());
        $decodedUri = rawurldecode($rawUri);

        if ($rule = $this->matchPatterns($rawUri, self::URI_QUERY_PATTERNS)) {
            return $this->block($request, $rule, 'uri');
        }
        if ($decodedUri !== $rawUri && ($rule = $this->matchPatterns($decodedUri, self::URI_QUERY_PATTERNS))) {
            return $this->block($request, $rule, 'uri_decoded');
        }

        // Inspect all query parameters
        if ($hit = $this->scanArray($request->query->all(), self::URI_QUERY_PATTERNS)) {
            return $this->block($request, $hit['rule'], 'query:' . $hit['key']);
        }

        // Inspect request body scalars for state-changing requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($hit = $this->scanArray($request->request->all(), self::BODY_PATTERNS, true)) {
                return $this->block($request, $hit['rule'], 'body:' . $hit['key']);
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $patterns
     * @return array{rule: string, key: string}|null
     */
    private function scanArray(array $data, array $patterns, bool $skipSensitiveKeys = false, string $prefix = '', int $depth = 0): ?array
    {
        if ($depth > 8) {
            return null;
        }

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            $fullKey = $prefix === '' ? $keyStr : "{$prefix}.{$keyStr}";

            if ($skipSensitiveKeys && in_array(strtolower($keyStr), self::SKIP_BODY_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->scanArray($value, $patterns, $skipSensitiveKeys, $fullKey, $depth + 1);
                if ($nested !== null) {
                    return $nested;
                }
            } elseif (is_string($value) && $value !== '') {
                // Only inspect up to 16 KB per field to keep latency near zero
                $sample = strlen($value) > 16384 ? substr($value, 0, 16384) : $value;
                if ($rule = $this->matchPatterns($sample, $patterns)) {
                    return ['rule' => $rule, 'key' => $fullKey];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private function matchPatterns(string $value, array $patterns): ?string
    {
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $value) === 1) {
                return $name;
            }
        }

        return null;
    }

    private function block(Request $request, string $rule, string $location): Response
    {
        try {
            AuditLogger::log(
                $request->user(),
                'security.waf_blocked',
                'blocked',
                null,
                null,
                [
                    'rule' => $rule,
                    'location' => $location,
                    'path' => substr($request->path(), 0, 200),
                    'method' => $request->method(),
                ],
                $request->ip(),
                null,
                $request->userAgent()
            );
        } catch (\Throwable) {
            // Never fail open if audit logging encounters an error
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Request blocked by security policy.',
            ], 403);
        }

        return response('Forbidden: Request blocked by security policy.', 403, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
