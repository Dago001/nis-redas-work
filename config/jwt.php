<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Signing Secret & Key Rotation
    |--------------------------------------------------------------------------
    |
    | Production environments MUST provide a strong JWT_SECRET (>= 32 raw bytes,
    | optionally prefixed with "base64:"). No insecure fallback is ever used.
    | During key rotation, place retired verification keys in
    | JWT_PREVIOUS_SECRETS as a comma-separated list.
    |
    */
    'secret' => env('JWT_SECRET'),

    'previous_secrets' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('JWT_PREVIOUS_SECRETS', ''))
    ))),

    'issuer' => (string) env('JWT_ISSUER', 'nis-redas'),

    'audience' => (string) env('JWT_AUDIENCE', 'nis-redas-api'),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),

    'leeway' => (int) env('JWT_LEEWAY', 30),

    'algorithm' => 'HS256',
];
