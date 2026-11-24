<?php

namespace App\Services;

use App\Models\PrimaryLocationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GeolocationService
{
    private const CANONICAL_STATES = [
        'AB' => 'Abia',
        'AD' => 'Adamawa',
        'AK' => 'Akwa Ibom',
        'AN' => 'Anambra',
        'BA' => 'Bauchi',
        'BY' => 'Bayelsa',
        'BE' => 'Benue',
        'BO' => 'Borno',
        'CR' => 'Cross River',
        'DE' => 'Delta',
        'EB' => 'Ebonyi',
        'ED' => 'Edo',
        'EK' => 'Ekiti',
        'EN' => 'Enugu',
        'FC' => 'FCT',
        'GO' => 'Gombe',
        'IM' => 'Imo',
        'JI' => 'Jigawa',
        'KD' => 'Kaduna',
        'KN' => 'Kano',
        'KT' => 'Katsina',
        'KE' => 'Kebbi',
        'KO' => 'Kogi',
        'KW' => 'Kwara',
        'LA' => 'Lagos',
        'NA' => 'Nasarawa',
        'NI' => 'Niger',
        'OG' => 'Ogun',
        'ON' => 'Ondo',
        'OS' => 'Osun',
        'OY' => 'Oyo',
        'PL' => 'Plateau',
        'RI' => 'Rivers',
        'SO' => 'Sokoto',
        'TA' => 'Taraba',
        'YO' => 'Yobe',
        'ZA' => 'Zamfara',
    ];

    /**
     * Resolve a request IP to a normalized location using HTTPS-only providers.
     *
     * @return array{country:string,state:string,state_code:string,provider:string}
     */
    public static function resolve(string $ip): array
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return self::unknownResult();
        }

        if (self::isPrivateIp($ip)) {
            if (! (bool) config('geolocation.allow_private_fallback', false)) {
                return self::unknownResult();
            }

            $fallbackState = strtoupper((string) SettingService::get('geolocation_fallback_state', 'FC'));

            return [
                'country' => 'Nigeria',
                'state' => self::stateNameFromCode($fallbackState) ?? 'FCT',
                'state_code' => $fallbackState,
                'provider' => 'fallback-private',
            ];
        }

        $cacheKey = 'geoip.v2.'.md5($ip);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['provider'] ?? 'unknown') !== 'unknown') {
            return $cached;
        }

        $configuredProvider = SettingService::get('geolocation_provider')
            ?: (string) config('geolocation.provider', 'ipapi_co');
        $provider = self::normalizeProvider((string) $configuredProvider);

        $location = match ($provider) {
            'ipapi_co' => self::resolveIpapiCo($ip),
            'ip_api_pro' => self::resolveIpApiPro($ip),
            'ipinfo' => self::resolveIpinfo($ip),
            default => null,
        };

        if ($location === null) {
            // Never cache unknown/failed responses and never silently fall back to FC.
            return self::unknownResult();
        }

        $ttlMinutes = SettingService::getInt('geolocation_cache_ttl', (int) config('geolocation.cache_ttl', 1440));
        if ($ttlMinutes > 0) {
            Cache::put($cacheKey, $location, now()->addMinutes($ttlMinutes));
        }

        return $location;
    }

    /**
     * @return array{country:string,state:string,state_code:string,provider:string}
     */
    public static function unknownResult(): array
    {
        return [
            'country' => 'Unknown',
            'state' => 'Unknown',
            'state_code' => '',
            'provider' => 'unknown',
        ];
    }

    private static function normalizeProvider(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'ip-api', 'ip_api_pro' => 'ip_api_pro',
            'ipapi', 'ipapi_co' => 'ipapi_co',
            'ipinfo' => 'ipinfo',
            default => 'ipapi_co',
        };
    }

    private static function providerUrl(string $provider, string $ip): ?string
    {
        $template = config("geolocation.providers.{$provider}");
        if (! is_string($template) || ! str_starts_with(strtolower($template), 'https://')) {
            Log::warning('Rejected non-HTTPS or unconfigured geolocation provider URL', [
                'provider' => $provider,
            ]);

            return null;
        }

        return str_replace('{ip}', rawurlencode($ip), $template);
    }

    private static function httpClient()
    {
        $timeout = max(1, (int) config('geolocation.timeout', 3));
        $retries = max(0, (int) config('geolocation.retries', 1));
        $sleepMs = max(50, (int) config('geolocation.retry_sleep_ms', 200));

        return Http::timeout($timeout)
            ->retry($retries, $sleepMs, throw: false)
            ->acceptJson();
    }

    /**
     * Resolve location using ipapi.co over HTTPS.
     *
     * @return array{country:string,state:string,state_code:string,provider:string}|null
     */
    private static function resolveIpapiCo(string $ip): ?array
    {
        $url = self::providerUrl('ipapi_co', $ip);
        if ($url === null) {
            return null;
        }

        try {
            $token = self::apiToken();
            $request = self::httpClient();
            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ! empty($data['error'])) {
                return null;
            }

            return self::normalizeLocation(
                country: $data['country_name'] ?? $data['country'] ?? null,
                region: $data['region'] ?? null,
                regionCode: $data['region_code'] ?? null,
                provider: 'ipapi_co'
            );
        } catch (\Throwable $e) {
            Log::warning('Geolocation lookup failed', [
                'provider' => 'ipapi_co',
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve location using ip-api.com Pro over HTTPS (requires API key).
     *
     * @return array{country:string,state:string,state_code:string,provider:string}|null
     */
    private static function resolveIpApiPro(string $ip): ?array
    {
        $token = self::apiToken();
        if ($token === null) {
            Log::warning('Geolocation provider ip_api_pro requires an API key', [
                'provider' => 'ip_api_pro',
            ]);

            return null;
        }

        $url = self::providerUrl('ip_api_pro', $ip);
        if ($url === null) {
            return null;
        }

        try {
            $response = self::httpClient()
                ->withQueryParameters([
                    'fields' => 'status,country,regionName,region,city',
                    'key' => $token,
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ($data['status'] ?? '') !== 'success') {
                return null;
            }

            return self::normalizeLocation(
                country: $data['country'] ?? null,
                region: $data['regionName'] ?? $data['region'] ?? null,
                regionCode: $data['region'] ?? null,
                provider: 'ip_api_pro'
            );
        } catch (\Throwable $e) {
            Log::warning('Geolocation lookup failed', [
                'provider' => 'ip_api_pro',
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve location using ipinfo.io over HTTPS.
     *
     * @return array{country:string,state:string,state_code:string,provider:string}|null
     */
    private static function resolveIpinfo(string $ip): ?array
    {
        $url = self::providerUrl('ipinfo', $ip);
        if ($url === null) {
            return null;
        }

        try {
            $token = self::apiToken();
            $request = self::httpClient();
            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }

            $country = $data['country_name'] ?? $data['country'] ?? null;
            if ($country === 'NG') {
                $country = 'Nigeria';
            }

            return self::normalizeLocation(
                country: $country,
                region: $data['region'] ?? null,
                regionCode: null,
                provider: 'ipinfo'
            );
        } catch (\Throwable $e) {
            Log::warning('Geolocation lookup failed', [
                'provider' => 'ipinfo',
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate provider response fields and map region to a valid Nigerian state code.
     *
     * @return array{country:string,state:string,state_code:string,provider:string}|null
     */
    private static function normalizeLocation(mixed $country, mixed $region, mixed $regionCode, string $provider): ?array
    {
        if (! is_string($country) || trim($country) === '' || strlen($country) > 100) {
            return null;
        }

        if (! is_string($region) || trim($region) === '' || strlen($region) > 100) {
            return null;
        }

        $country = trim($country);
        $region = trim($region);

        $stateCode = self::stateCodeFromName($region);
        if ($stateCode === null && is_string($regionCode) && trim($regionCode) !== '') {
            $candidate = strtoupper(trim($regionCode));
            if (self::isKnownStateCode($candidate)) {
                $stateCode = $candidate;
            }
        }

        return [
            'country' => $country,
            'state' => $region,
            'state_code' => $stateCode ?? '',
            'provider' => $provider,
        ];
    }

    private static function apiToken(): ?string
    {
        $token = SettingService::get('geolocation_api_token') ?: config('geolocation.token');
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    private static function isKnownStateCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        if (self::hasSeededLookupTable()) {
            return PrimaryLocationCode::query()->where('code', $code)->exists();
        }

        return array_key_exists($code, self::CANONICAL_STATES);
    }

    private static function hasSeededLookupTable(): bool
    {
        try {
            return Schema::hasTable('primary_location_codes') && PrimaryLocationCode::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Map a detected region/state name to a Nigerian state code from the lookup table.
     */
    public static function stateCodeFromName(string $name): ?string
    {
        $normalized = self::normalizeStateName($name);
        if ($normalized === '') {
            return null;
        }

        if (self::hasSeededLookupTable()) {
            $code = PrimaryLocationCode::query()
                ->whereRaw('LOWER(location_name) = ?', [$normalized])
                ->value('code');

            return $code ? strtoupper($code) : null;
        }

        foreach (self::CANONICAL_STATES as $code => $stateName) {
            if (strtolower($stateName) === $normalized) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get the state name for a given state code.
     */
    public static function stateNameFromCode(string $code): ?string
    {
        $upper = strtoupper(trim($code));
        if ($upper === '') {
            return null;
        }

        if (self::hasSeededLookupTable()) {
            return PrimaryLocationCode::query()
                ->where('code', $upper)
                ->value('location_name');
        }

        return self::CANONICAL_STATES[$upper] ?? null;
    }

    private static function normalizeStateName(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Check if an IP address is private/local.
     */
    public static function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
