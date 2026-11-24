# NIS REDAS Security Remediation and System Assessment

**Date:** 2026-11-24  
**Repository:** `https://github.com/Dago001/nis-redas-work`  
**Remediation Branch:** `feature/redas-security-workflow-remediation`  
**Target Environment:** Laravel 12 (PHP 8.4.15, MySQL 8.4.7 / SQLite in-memory tests)

---

## 1. Confirmed Defects

Every finding below was reproduced directly against the linked local codebase before any application code was modified:

1. **API OTP Bypasses Password Authentication**
   - `routes/api.php` (lines 18–19) exposes `POST /api/otp/request` and `POST /api/otp/verify` publicly with no prior authenticated session or password-verified challenge token.
   - `app/Http/Controllers/ApiOtpController.php` (lines 18–101): `request()` accepts any `login` and `purpose` (`login` or `verify_email`), immediately generates an OTP, and emails it without password verification. Missing accounts throw a 422 `ValidationException` (`Account not found.`), permitting user enumeration.
   - `ApiOtpController::verify()` (lines 103–234) issues access and refresh JWTs solely upon matching a 4-digit code (`random_int(0, 9999)`), completely bypassing password verification, `is_enabled`, and challenge binding.
   - OTP codes are stored as unsalted `sha256` hashes; multiple active OTPs can coexist per user; `attempts` are incremented only on the latest active row and checked only after matching `code_hash`; consumption (`consumed_at`) is non-atomic (race/replay risk); `purpose=verify_email` marks verification intent without a password or challenge.
2. **API Login & Refresh Token Weaknesses (`app/Http/Controllers/ApiAuthController.php`)**
   - `login()` (lines 28–111) requires `role` in the request payload, does not verify `$user->is_enabled`, and generates a 4-digit OTP without returning a bound challenge token.
   - `refresh()` (lines 113–197) lacks DB row locking/atomic rotation, lacks refresh-token reuse detection and token-family revocation, omits `$user->is_enabled` checks, and copies `role` from the decoded old JWT rather than reloading the authoritative DB role.
   - Calls `env()` directly at runtime (lines 67, 174, 195), which breaks when Laravel configuration is cached (`php artisan config:cache`).
3. **Hardcoded Fallback JWT Secret & Incomplete JWT Validation (`app/Services/JwtService.php`, `app/Http/Middleware/JwtAccessTokenMiddleware.php`)**
   - `JwtService::fromEnv()` (lines 19–31) falls back to `'dev-fallback-jwt-secret-dev-fallback'` when `JWT_SECRET` is unset, allowing forged tokens in misconfigured deployments.
   - Tokens omit `aud` and `nbf` claims and lack key-rotation (`kid` / previous keys) support.
   - `JwtAccessTokenMiddleware` (lines 13–49) checks neither `$user->is_enabled` nor whether the user's token family/session remains active.
4. **Insecure Geolocation Transport, Fail-Open Fallback & Missing Proxy Trust (`app/Services/GeolocationService.php`, `bootstrap/app.php`)**
   - `GeolocationService::resolveIpApi()` (line 64) calls `http://ip-api.com/json/{$ip}` over plaintext HTTP and interpolates `$ip` directly into the URL without IP validation.
   - `resolveIpapiCo()` (line 103) appends the API token in the URL query string (`?token=...`).
   - When geolocation fails or returns unknown (`resolve()` lines 42–49), the service falls back to `geolocation_fallback_state` (default `FC`), creating a fail-open bypass for FCT-assigned accounts.
   - `bootstrap/app.php` does not configure trusted proxies, exposing `request()->ip()` to header spoofing if proxy headers are ever forwarded or trusted broadly.
5. **Null Reviewer Organisational Scope Grants Unrestricted Access (`app/Services/SubmissionWorkflow.php`, `app/Http/Controllers/SubmissionReviewController.php`)**
   - `SubmissionWorkflow::pendingQueryForApprover()` (lines 146–160) only filters by `scope_code`/`zonal_code` when `$scopeCode !== null`. Any `desk_admin`, `directorate_admin`, `cgis_desk_admin`, or `zonal_commander` lacking a scope code receives all pending submissions at that stage nationwide.
   - The same `if ($scopeCode !== null)` bypass exists in `SubmissionReviewController::authorizeScope()` (lines 286–301), `index()` dashboard metrics/approved lists (lines 42–61), `reportQuery()` (lines 322–324), `Web\DashboardController::downloadSubmissionPdf()` (lines 904–910), the `/user/archive` route closure (`routes/web.php` lines 139–146), and `SubmissionWorkflow::notifyPendingReviewers()` (lines 320–324).
   - Reviewers are not blocked from approving their own submissions if they also match stage/scope; approvals and rejections execute without transactions or pessimistic locks (`lockForUpdate`).
6. **Stubbed Archive Upload (`routes/web.php` lines 157–168)**
   - `POST /user/archive/upload` validates form inputs and uploaded files, then discards the files and redirects with `'Document(s) uploaded to archive successfully.'` without persisting files or metadata.
7. **Stubbed Report Generation (`routes/web.php` lines 174–185)**
   - `POST /user/reports/generate` validates parameters and redirects with `'Your report has been generated and is ready for download.'` without generating any PDF, Excel, or CSV file.
8. **CSV Formula Injection & XLSX Typing Issues (`app/Http/Controllers/SubmissionReviewController.php`, `app/Services/ExcelReportService.php`)**
   - `SubmissionReviewController::streamReportCsv()` and `flattenForCsv()` write raw cell values with `fputcsv()` without prefixing leading `=`, `+`, `-`, `@`, tab, or CR characters (CSV formula injection risk).
   - `ExcelReportService::writeXlsx()` writes every cell as `t="inlineStr"` (converting numeric metrics to text), stores rows in a single concatenated string in memory, and does not sanitize formula-leading strings.
9. **File Storage & Record Lifecycle Weaknesses (`config/filesystems.php`, `app/Http/Controllers/Web/DashboardController.php`)**
   - `config/filesystems.php` sets `'serve' => true` on the `local` disk.
   - Uploads permit legacy binary formats (`doc`, `docx`, `xls`) without a malware-scanner extension point or MIME-vs-extension verification; `document()` does not validate paths against traversal (`..`) or allowed prefixes.
   - `Web\DashboardController::destroySubmission()` hard-deletes submission rows and uploaded files.
   - `Web\DashboardController::updateSubmission()` overwrites `return_data` in place without preserving version history and drops previously uploaded attachments when new files are not re-uploaded.
   - `applications.user_id` uses `cascadeOnDelete()` at the schema level with no model-level guard preventing deletion of users who submitted official records.
10. **Missing Option C Reporting Architecture & Statistical Data Model**
    - All statistical values and `report_period` exist solely inside untyped JSON `applications.return_data`.
    - No `report_versions` or structured `reporting_indicators` / `report_indicator_values` tables exist.
    - No `reporting_units` hierarchy table exists (`2026_08_13_000000_create_directorate_formation_zone_headquarters_cgis_unit_tables.php` is empty); state-to-zone mapping is inferred only when a `desk_admin` approves a submission.
    - `Admin\ConsolidationController` is a read-only status count summary rather than a separate consolidated report entity with source lineage, exclusions, gaps, version revisions, staleness tracking, and HQ approval workflow.
    - No PRS central reporting dashboard exists showing expected vs. received original and consolidated reports side-by-side (`5 + 1 = 6`, `4 + 1 = 5`).
11. **Audit Log Mutability, Missing Coverage & Unredacted Details (`app/Models/AuditLog.php`, `app/Services/AuditLogger.php`)**
    - `AuditLog` permits `UPDATE` and `DELETE` without model guards or integrity chaining.
    - `AuditLogger::log()` does not redact sensitive keys or capture `scope_code` / `request_id` / route context.
    - `UserManagementController`, submission workflow actions, document downloads, and report exports lack audit coverage; `HqAdminController::generateReport()` creates `AuditLog` directly bypassing `AuditLogger`.
12. **Notification Reliability & PII Logging (`app/Services/Messaging/ResendMailService.php`, `app/Listeners/*`)**
    - Queued listeners (`SendSubmissionCreatedNotification`, `SendSubmissionStageAdvancedNotification`, `SendSubmissionRejectedNotification`) lack idempotency keys, retry/backoff policies, and delivery state tracking (`accepted` vs `failed`).
    - `ResendMailService` logs plaintext recipient email addresses on failure.
13. **Default Passwords in Seeders & Composer Dependency Constraints**
    - `DatabaseSeeder`, `NewSeeder`, and `TestUserSeeder` seed accounts with hardcoded `'password123'` unconditionally (even outside local/testing).
    - `composer.json` uses wildcard `*` constraints for `bacon/bacon-qr-code`, `barryvdh/laravel-dompdf`, and `pragmarx/google2fa`, and declares `"php": "^8.2"` while `composer.lock` includes packages requiring PHP `>=8.4.1`.
14. **Repository Hygiene & Performance**
    - `.env.example` and CI workflows were absent; `.gitignore` contained `readme.md` (ignoring `README.md` on Windows) and omitted `.phpunit.result.cache`, `tmp-js-check/`, `/public/build`, `/public/storage`, and `database/*.sqlite*`.
    - `Admin\ConsolidationController::index()` executes 24 separate monthly count queries in a loop; dashboard lists use `limit(50)` without pagination; `applications` lacks indexes on `(status, created_at)` and `(category, report_period, scope_code)`.

---

## 2. Existing Functional Components

- **Web Authentication & MFA:** `AuthController` (email/service-number login, rate limiting, email verification check, ABAC geo policy, forced password change), `MfaController` + `MfaService` (TOTP setup with QR SVG, 8 encrypted single-use backup codes), `AuthTokenController` (single-use magic links for email verification and password reset).
- **Access Control Middleware:** `CheckAccess` (`access:category=...,location=...,role=...,minLevel=...`), `AbacGeolocationMiddleware`, `RequireMfaPending`, `RequirePasswordChange`, `LogSuspiciousActivity`.
- **Submission Forms & Review Queues:** Combined 10-section state return form, 10 standalone directorate forms, 7 CGIS unit forms, `SubmissionWorkflow` state machine (`desk_review` → `zonal_review` → `hq_review` → `approved` for states; `directorate_review` → `hq_review` → `approved` for directorates; `cgis_desk_review` → `hq_review` → `approved` for CGIS units), `ApplicationComment` review thread, `SubmissionReviewController`, `HqAdminController`, `SuperAdminController`, `AdminSettingController`, `AdminAuditLogController`, `UserManagementController`, `DirectoryController`.
- **Exports & Notifications:** `ReportPdfService` (DomPDF single submission & filtered report PDFs), `ExcelReportService` (ZipArchive XLSX writer for HQ quarterly/biannual/annual exports), `UserNotification` DB notifications + API, `ResendMailService` + `TemplateRenderer`.

---

## 3. Baseline Automated Test Coverage (Pre-Remediation)

Executed prior to code modifications via `php -d xdebug.mode=off artisan test` (SQLite `:memory:`, `phpunit.xml`):

- **Total:** 118 tests (544 assertions) — **113 passed, 5 failed** (Duration: 36.71s).
- **Pre-existing failures observed at baseline:**
  1. `AuthLoginTest::test_login_fails_when_role_does_not_match_user_record` — web login deliberately removed role selection per `TODO.md`, so mismatched `role` input redirected to `/mfa/setup` instead of `/login`.
  2. `AuthRegistrationTest::test_users_can_register_with_a_supported_role_selection` — `POST /register` returns `405` because self-service registration was disabled (`GET /register` redirects to `/login`).
  3. `RegistrationFormFieldsTest::test_registration_form_exposes_location_and_access_profile_fields` — `GET /register` returns `302` redirect to `/login`.
  4. `OtpApiTest::test_otp_request_creates_otp_code_and_sends_email_via_resend_transport` — returned `502` because `RESEND_API_KEY` is unset and `ResendMailService` requires it even under `Mail::fake()`.
  5. `StateCombinedFormTest::test_combined_form_renders_all_ten_directorate_sections` — `name="data_consent"` appeared twice in the combined state form HTML (once in `_return-layout.blade.php` and once in `sections/investigation.blade.php`).

---

## 4. Recommended Implementation Plan

1. **Phase 1 (Authentication & Secrets):** Introduce `config/jwt.php` and `config/otp.php`; harden `JwtService` with fail-closed validation, `kid` key rotation, `aud`/`nbf`/`typ`/`fam` claims, and production boot assertions; implement `auth_challenges` table and `ApiLoginService` enforcing password-bound 6-digit HMAC-hashed OTP challenges, atomic attempt/consumption locking, refresh-token family reuse detection, and `is_enabled` checks in middleware.
2. **Phase 2 (Geolocation, Proxies, Reviewer Scope):** Enforce HTTPS-only geolocation providers with strict response validation, fail-closed unknown location handling, and explicit `TRUSTED_PROXIES`; introduce `ReportAccess` and `ApplicationPolicy` enforcing deny-by-default scope checks, self-approval blocking, and pessimistic row locking during workflow actions.
3. **Phase 3 (Option C Schema & Services):** Add non-destructive additive migrations for `reporting_units`, `report_versions`, `reporting_indicators`, `report_indicator_values`, `consolidated_reports`, `consolidated_report_revisions`, `consolidated_report_sources`, `archive_documents`, `generated_reports`, `notification_deliveries`, and extended `applications` / `audit_logs` columns; implement `IndicatorExtractor`, `ReportVersioning`, `ConsolidationService`, `CompletenessService`, and idempotency keys.
4. **Phase 4 (Option C UI, PRS Dashboard, Archive & Reports):** Implement `ConsolidatedReportController`, HQ consolidated approval routes, `PrsDashboardController`, `ArchiveController` + `SecureFileStorage` + `MalwareScanner`, and `ReportGenerationController` + `GenerateReportJob` (PDF, XLSX, CSV).
5. **Phase 5 (Exports, Notifications, Audit, Performance):** Add `CsvWriter` formula-injection sanitization, typed streaming `ExcelReportService::writeSheet()`, idempotent queued `NotificationDispatcher` / `SendTemplatedEmailJob`, immutable hash-chained `AuditLogger` with redaction and verify/prune commands, query optimization (`DbExpr::yearMonth`), and tightened `composer.json` constraints.
6. **Phase 6–8 (QA, CI/CD, Documentation & Git):** Expand automated test suite across all security, workflow, export, and reliability scenarios; run SQLite and MySQL 8.4 checks plus Vite build and browser walkthrough; configure GitHub Actions CI; update project documentation and `docs/REMEDIATION_COMPLETION_REPORT.md`; push branch `feature/redas-security-workflow-remediation`.

---

## 5. Risks Requiring Organisational Approval

1. **PRS Viewer Identification:** PRS dashboard access is scoped to `directorate_admin` users assigned to `prs` plus national HQ viewers (`hq_admin`, `admin`, `super_admin`). Confirm whether additional PRS officer roles require read access.
2. **Official State-to-Zone Mapping:** State-to-zone hierarchy is seeded from existing `desk_admin` `assigned_zonal_command_code` provisioning (`redas:reporting-units:sync-zones`) rather than hardcoded assumptions; HQ must verify official NIS zone assignments for all 37 formations.
3. **Directorate Sections & Sub-Directorates:** Directorate sub-sections are not modeled in the legacy database; HQ must populate `reporting_units` section records using the provided management commands before section-level expected-vs-received percentages can be computed for directorates.
4. **Provisional Statistical Indicators:** Automatically extracted numeric indicators are registered with `status = provisional`; PRS analysts must review, standardize definitions/units, and approve indicators for official national publications.
5. **Consolidated Report Final Approver:** Zonal and Directorate Consolidated Reports currently route to `hq_admin` for final approval (`hq_review`). Confirm if CGIS/executive sign-off is required.
6. **Retention Periods:** Archive documents and audit logs default to a 2,555-day (~7-year) retention policy (`ARCHIVE_RETENTION_DAYS`, `AUDIT_RETENTION_DAYS`); legal/compliance must confirm statutory retention schedules.
7. **API OTP Breaking Change:** API OTP length increases from 4 digits to 6 digits and `POST /api/otp/request` / `/api/otp/verify` now require a password-verified `challenge_token`. Mobile/API clients must be updated accordingly.
8. **Removal of `verify_email` API OTP Purpose:** Email verification via unauthenticated `/api/otp/*` is removed; email verification uses signed magic links (`AuthTokenController::verifyEmail`).
9. **Seeder Passwords:** Legacy seeders (`DatabaseSeeder`, `NewSeeder`, `TestUserSeeder`) use `'password123'` for local/testing convenience only; non-local environments must generate random credentials or provision accounts via `/admin/users`.
