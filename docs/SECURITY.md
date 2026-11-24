# NIS REDAS Security Architecture & Controls

> **Note:** Controls below are engineered using OWASP ASVS, OWASP Top 10, OWASP API Security Top 10, NIST CSF 2.0, and ISO/IEC 27001 as technical reference standards. Implementation of these controls does not constitute formal third-party certification or compliance attestation.

## 1. API Authentication & Password-Bound OTP Challenge

All API authentication flows follow a strict two-stage sequence (`App\Services\Auth\ApiLoginService`):

1. Client submits `POST /api/login` with `login` (email or service number) and `password`.
2. Server enforces per-identity+IP rate limiting (5 failures / 15 minutes) and performs constant-time password verification (including a dummy bcrypt check when the account does not exist to prevent user enumeration).
3. Server verifies `is_enabled === true` and `email_verified_at !== null`.
4. Server creates a short-lived `AuthChallenge` with a 32-byte random `challenge_token` (stored only as SHA-256) and a cryptographically random 6+ digit OTP (stored as `HMAC-SHA256(challenge_id|otp, APP_KEY)`).
5. Client submits `POST /api/otp/verify` with `challenge_token` and `otp`. Attempt increments (`attempts < max_attempts`) and consumption (`consumed_at`) are atomic inside a database transaction, blocking brute-force races and replays.
6. Only upon successful verification does the server issue an HS256 JWT access token and a rotating refresh token bound to a `family_id`.

Unauthenticated callers cannot request or verify an OTP using only a login identifier.

## 2. JWT Secret Management & Key Rotation

`App\Services\JwtService` fails closed whenever `JWT_SECRET` is absent, shorter than 32 raw bytes, malformed base64, or matches a known placeholder. In production (`APP_ENV=production`), `AppServiceProvider::boot()` executes `JwtService::assertConfigured()` on HTTP boot.

### Generating a New Secret
```bash
php -r "echo 'JWT_SECRET=base64:' . base64_encode(random_bytes(48)) . PHP_EOL;"
```

### Zero-Downtime Secret Rotation
1. Move the current `JWT_SECRET` value into `JWT_PREVIOUS_SECRETS`.
2. Set the newly generated secret as `JWT_SECRET`.
3. Clear and rebuild config cache (`php artisan config:cache`).
4. Tokens carry a `kid` header (`substr(sha256(rawKey), 0, 16)`); new tokens sign with `JWT_SECRET` while existing tokens verify against `JWT_PREVIOUS_SECRETS` until they expire.
5. Remove the retired secret from `JWT_PREVIOUS_SECRETS` after the maximum refresh token TTL elapses.

### Refresh Token Family Reuse Detection
Refresh tokens are stored solely as SHA-256 hashes in `refresh_tokens` with `family_id` and `replaced_by_jti`. Presenting an already-rotated refresh token triggers immediate revocation of the entire token family (invalidating both refresh and active access tokens in that family) and records `api.refresh.reuse_detected`.

## 3. Geolocation & Trusted Proxies

- `GeolocationService` enforces HTTPS-only provider URLs (`ipapi.co`, `pro.ip-api.com`, `ipinfo.io`), validates IPs with `FILTER_VALIDATE_IP`, applies strict timeouts/retries, validates region codes against `primary_location_codes`, and never caches or falls back to `FC` on failed/unknown responses.
- `bootstrap/app.php` trusts only explicitly configured `TRUSTED_PROXIES` (empty by default; wildcard `*` is rejected).

## 4. Deny-by-Default Organisational Scope Authorization

`App\Services\ReportAccess` and `App\Policies\ApplicationPolicy` enforce deny-by-default access across all review queues, views, downloads, and exports:
- Reviewers (`desk_admin`, `directorate_admin`, `cgis_desk_admin`, `zonal_commander`) with null or empty scope receive empty query results (`1 = 0`) and HTTP `403` on direct URL access.
- Self-approval (`actor->id === application->user_id`) is explicitly blocked.
- Workflow transitions execute inside `DB::transaction` with `lockForUpdate()` and return `409 Conflict` on concurrent stage transitions.

## 5. Private File Storage & Exports

- All uploaded supporting documents, archive files, and generated reports reside on the private `local` disk (`storage/app/private`, `'serve' => false`) with randomized UUID filenames.
- `SecureFileStorage` validates extensions against an allow-map, inspects real file content MIME types via `finfo`, rejects path traversal (`..`, absolute paths, null bytes, unauthorized prefixes), verifies SHA-256 checksums on archive downloads (`409` on mismatch), sets `X-Content-Type-Options: nosniff`, and provides a `MalwareScanner` hook (`NullMalwareScanner` or `ClamAvSocketScanner`).
- CSV and Excel exports neutralize spreadsheet formula-injection triggers (`=`, `+`, `-`, `@`, tab, CR).

## 6. Immutable Hash-Chained Audit Logging

- `AuditLog` blocks Eloquent `UPDATE` and `DELETE` via model events (except controlled retention pruning via `redas:audit:prune --force`).
- `AuditLogger` recursively redacts sensitive keys (`password`, `otp`, `token`, `secret`, `authorization`, `api_key`, `mfa`), records `scope_code`, `request_id`, `http_method`, `route_name`, and chains entries with SHA-256 (`prev_hash` + `entry_hash`).
- Run `php artisan redas:audit:verify` periodically to verify chain integrity.
