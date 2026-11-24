# NIS REDAS System Architecture

## Layer Overview

- **Presentation Layer:** Blade server-rendered templates with Vite 7 assets (`resources/views/`, `resources/js/`, `resources/css/`), role-scoped layouts (`partials/header`, `desk-admin/layout`, `admin/headquarters/layout`, `super-admin/layout`), and PWA manifest.
- **HTTP & Security Middleware Layer:** `routes/web.php` (session + CSRF + TOTP MFA + `CheckAccess` + `AbacGeolocationMiddleware`), `routes/api.php` (throttled password-bound OTP challenge + `JwtAccessTokenMiddleware`), explicit `TRUSTED_PROXIES` in `bootstrap/app.php`.
- **Authorization Layer:** `App\Services\ReportAccess` and `App\Policies\ApplicationPolicy` plus Gates (`viewPrsDashboard`, `manageConsolidated`) enforcing deny-by-default organisational scope.
- **Domain Services Layer:**
  - `App\Services\Auth\ApiLoginService` & `App\Services\JwtService`
  - `App\Services\GeolocationService`
  - `App\Services\SubmissionWorkflow`
  - `App\Services\Reporting\IndicatorExtractor`, `ReportVersioning`, `ConsolidationService`, `CompletenessService`
  - `App\Services\Files\SecureFileStorage` & `MalwareScanner`
  - `App\Services\Messaging\NotificationDispatcher` & `ResendMailService`
  - `App\Services\AuditLogger`
  - `App\Services\ExcelReportService`, `App\Services\ReportPdfService`, `App\Support\CsvWriter`
- **Asynchronous Processing:** Database-backed Laravel queue executing `GenerateReportJob` (PDF/XLSX/CSV generation) and `SendTemplatedEmailJob` (idempotent email dispatch with bounded exponential backoff).
- **Persistence Layer:** MySQL 8.4 (production) / SQLite (automated testing) with normalized tables for `reporting_units`, `applications`, `report_versions`, `reporting_indicators`, `report_indicator_values`, `consolidated_reports`, `consolidated_report_revisions`, `consolidated_report_sources`, `archive_documents`, `generated_reports`, `notification_deliveries`, `auth_challenges`, `refresh_tokens`, and hash-chained `audit_logs`.
