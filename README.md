# NIS REDAS — Reporting, Evaluation and Data Analysis System

Laravel 12 web application and companion JSON API for the Nigeria Immigration Service (NIS) Reporting, Evaluation and Data Analysis System (REDAS).

## Key Capabilities

- **Option C Reporting Architecture:** Individual original formation/section reports are preserved independently alongside separately prepared Zonal Consolidated Reports and Directorate Consolidated Reports. PRS receives and inspects both categories (`5 originals + 1 zonal consolidated = 6 records`; `4 originals + 1 directorate consolidated = 5 records`).
- **Immutable Report Versioning & Structured Indicators:** Every submission and resubmission creates an immutable `report_versions` snapshot and extracts structured numeric indicators into `reporting_indicators` and `report_indicator_values`. Source edits automatically flag dependent consolidated reports as stale without mutating prior revisions.
- **Hardened Authentication & Authorization:** Mandatory TOTP MFA for web sessions, password-bound 6-digit HMAC-hashed OTP challenges for API clients, fail-closed JWT secret validation with `kid` key rotation and refresh-token family reuse detection, HTTPS-only geolocation ABAC with explicit trusted-proxy configuration, and deny-by-default organisational scope policies (`ReportAccess` + `ApplicationPolicy`).
- **Private Document Archive & Real Report Generation:** Private storage (`storage/app/private`) with UUID filenames, finfo MIME verification, SHA-256 integrity checks, optional ClamAV scanning, and queued/synchronous PDF, typed Excel (`.xlsx`), and formula-safe CSV exports.
- **Immutable Hash-Chained Audit Trail & Reliable Queued Notifications:** Append-only `audit_logs` with sensitive-key redaction, SHA-256 hash chaining (`redas:audit:verify`), controlled retention pruning (`redas:audit:prune`), and idempotent queued email delivery tracking (`notification_deliveries`).

## Requirements

- PHP `^8.4` (`mbstring`, `pdo_mysql`, `pdo_sqlite`, `zip`, `gd`, `intl`, `fileinfo`, `bcmath`)
- Composer 2.x
- MySQL 8.4+ (production) / SQLite (local/unit testing)
- Node.js 22+ and npm (Vite 7 asset build)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# Generate a strong 48-byte JWT signing key:
php -r "echo 'JWT_SECRET=base64:' . base64_encode(random_bytes(48)) . PHP_EOL;"
# Add the generated JWT_SECRET to .env
php artisan migrate --seed
php artisan redas:reporting-units:sync-zones
npm ci && npm run build
```

## Key Environment Variables

See `.env.example` for the full list:

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel AES-256-CBC encryption key |
| `JWT_SECRET` | Required >= 32-byte signing key (`base64:` supported); production fails closed if absent or weak |
| `JWT_PREVIOUS_SECRETS` | Comma-separated retired JWT secrets for zero-downtime key rotation |
| `OTP_DIGITS` / `OTP_TTL_SECONDS` | API OTP length (min 6) and challenge lifetime (default 300s) |
| `GEOLOCATION_PROVIDER` / `GEOLOCATION_API_TOKEN` | HTTPS-only geolocation provider (`ipapi_co`, `ip_api_pro`, `ipinfo`) |
| `TRUSTED_PROXIES` | Comma-separated trusted reverse proxy IPs/CIDRs (empty trusts none; never `*`) |
| `RESEND_API_KEY` | Resend transactional email API key |
| `QUEUE_CONNECTION` | `database` (recommended) |
| `AUDIT_RETENTION_DAYS` | Audit retention horizon in days (default `2555`) |
| `MALWARE_SCANNER` / `CLAMAV_SOCKET` | Optional ClamAV socket scanner (`none` or `clamav`) |

## Running Queue Workers & Scheduler

```bash
php artisan queue:work --tries=5 --backoff=30,120,600,1800
php artisan schedule:run   # Run every minute via cron / Task Scheduler
```

## Automated Testing

```bash
php artisan redas:security:check
php artisan test
```

## Documentation

- [Remediation Assessment](docs/REMEDIATION_ASSESSMENT.md)
- [Remediation Completion Report](docs/REMEDIATION_COMPLETION_REPORT.md)
- [Security Architecture & Controls](docs/SECURITY.md)
- [Option C Reporting Workflow](docs/REPORTING_WORKFLOW.md)
- [Deployment, Backup & Recovery](docs/DEPLOYMENT.md)
- [System Architecture](docs/ARCHITECTURE.md)
- [Backend Schema](docs/BACKEND_SCHEMA.md)

## License

MIT License (per `composer.json`).
