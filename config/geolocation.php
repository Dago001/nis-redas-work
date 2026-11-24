<?php

return [
    'provider' => env('GEOLOCATION_PROVIDER', 'ipapi_co'),

    'providers' => [
        'ipapi_co' => 'https://ipapi.co/{ip}/json/',
        'ip_api_pro' => 'https://pro.ip-api.com/json/{ip}',
        'ipinfo' => 'https://ipinfo.io/{ip}/json',
    ],

    'token' => env('GEOLOCATION_API_TOKEN'),

    'timeout' => max(1, (int) env('GEOLOCATION_TIMEOUT', 3)),

    'retries' => max(0, (int) env('GEOLOCATION_RETRIES', 1)),

    'retry_sleep_ms' => max(50, (int) env('GEOLOCATION_RETRY_SLEEP_MS', 200)),

    'cache_ttl' => max(0, (int) env('GEOLOCATION_CACHE_TTL', 1440)),

    'allow_private_fallback' => filter_var(
        env('GEOLOCATION_ALLOW_PRIVATE_FALLBACK', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        FILTER_VALIDATE_BOOLEAN
    ),

    'unknown_policy' => env('GEOLOCATION_UNKNOWN_POLICY', 'deny'),
];
