# NIS REDAS Security Remediation & Reconstruction Completion Report

**Date:** 2026-11-24  
**Repository:** `https://github.com/Dago001/nis-redas-work`  
**Branch:** `feature/redas-security-workflow-remediation`  

---

## 1. Confirmed Findings Remediated

| Area | Finding Confirmed | Remediation Implemented |
|---|---|---|
| API OTP Auth | Public `/api/otp/request` & `/api/otp/verify` bypassed password auth, used 4-digit unsalted hashes, leaked account existence, and had non-atomic consumption | Implemented `auth_challenges` table + `ApiLoginService`: password-first verification with constant-time dummy check, 6-digit HMAC-SHA256 OTP bound to `challenge_token`, atomic attempt increment & consumption, resend limits, `is_enabled` + `email_verified_at` + `must_change_password` enforcement |
| JWT Secrets | Hardcoded fallback `dev-fallback-jwt-secret-dev-fallback`, missing `aud`/`nbf`/rotation, no family revocation | Rewrote `JwtService` to fail closed (`JwtConfigurationException`), added production boot check, `kid` key rotation (`JWT_PREVIOUS_SECRETS`), `aud`/`nbf`/`typ`/`fam` validation, refresh-token family reuse detection, and `redas:security:check` |
| Geolocation & Proxies | Cleartext `http://ip-api.com`, token in query string, fail-open `FC` fallback, no trusted-proxy config | Rewrote `GeolocationService` (HTTPS-only providers, IP validation, retry/timeout, region validation against `primary_location_codes`, unknown returns `state_code=''`), configured explicit `TRUSTED_PROXIES` in `bootstrap/app.php` |
| Reviewer Scope | Null scope skipped `WHERE` filters in `SubmissionWorkflow`, `SubmissionReviewController`, `DashboardController`, `/user/archive`; allowed self-approval | Built `ReportAccess` + `ApplicationPolicy` (deny-by-default `1=0` on null scope), blocked self-approval, wrapped `approve`/`reject` in `DB::transaction` + `lockForUpdate()` (`409` on race) |
| Option C Workflow | No consolidated report entity, no version snapshots, JSON-only stats, no PRS dashboard | Added `reporting_units`, `report_versions`, `reporting_indicators`, `report_indicator_values`, `consolidated_reports`, `consolidated_report_revisions`, `consolidated_report_sources`, `ConsolidationService`, `CompletenessService`, `ConsolidatedReportController`, `HqConsolidatedController`, and `PrsDashboardController` |
| Archive Upload | `POST /user/archive/upload` validated and discarded files | Built `ArchiveController` + `ArchiveDocument` + `SecureFileStorage` (private UUID storage, `finfo` MIME check, SHA-256 integrity verification, optional ClamAV hook, path-traversal protection) |
| Report Generation | `POST /user/reports/generate` validated and redirected without output | Built `ReportGenerationController` + `GeneratedReport` + `GenerateReportJob` producing real scoped PDF, typed XLSX, and formula-safe CSV files |
| Exports Security | Raw `fputcsv` allowed CSV formula injection; XLSX wrote numbers as strings in memory | Added `CsvWriter` (UTF-8 BOM + formula prefixing) and streaming typed `ExcelReportService::writeSheet()` (`t="n"` numeric cells + formula sanitization) |
| Notifications | No idempotency, retries, backoff, or delivery status tracking; raw email logged | Added `notification_deliveries`, `NotificationDispatcher`, `SendTemplatedEmailJob` (5 tries, exponential backoff, `accepted` status + provider message ID, hashed recipient logs, no private report contents in emails) |
| Audit Logging | Mutable `AuditLog`, unredacted details, missing workflow/user/download/export events | Enforced append-only `AuditLog` model guards, recursive secret redaction, SHA-256 hash chain, `redas:audit:verify`, `redas:audit:prune`, `audit` daily log channel, and full event coverage |
| Performance & Hygiene | 24-query monthly loop in `ConsolidationController`, wildcard Composer constraints, missing `.env.example`/CI | Added portable `DbExpr::yearMonth()`, composite DB indexes, tightened `composer.json` (`php ^8.4`, no `*`), added `.env.example`, `.github/workflows/ci.yml`, and fixed `.gitignore` |

---

## 2. Database Schema Changes (Additive & Reversible)

15 new additive migrations (`2026_11_24_000001`–`000002`, `2026_11_24_100001`–`100013`) were added and verified for both forward migration (`migrate:fresh --seed`), rollback (`migrate:rollback --step=15`), and re-migration (`migrate`) on SQLite and MySQL 8.4.7:

- `auth_challenges`, `refresh_tokens` (`family_id`, `replaced_by_jti`, `reuse_detected_at`)
- `reporting_units`, `users.assigned_section_code`
- `applications` (`report_type`, `report_period`, `section_code`, `current_version`, `idempotency_key`, `deleted_at`, indexes + chunked `report_period` backfill)
- `report_versions` (+ `ReportVersionBackfill`), `reporting_indicators`, `report_indicator_values`
- `consolidated_reports`, `consolidated_report_revisions`, `consolidated_report_sources`
- `archive_documents`, `generated_reports`, `notification_deliveries`
- `audit_logs` (`scope_code`, `request_id`, `http_method`, `route_name`, `prev_hash`, `entry_hash`)

---

## 3. Automated Tests & Verification Results

| Check | Environment | Exact Result |
|---|---|---|
| PHP Syntax Lint (`php -l` all `.php` files) | PHP 8.4.15 | **Passed** (`LINT_OK`, 0 syntax errors) |
| `php artisan redas:security:check` | Local | **Passed** |
| `composer validate --strict` | Composer 2.10.1 | **Passed** (`./composer.json is valid`) |
| `composer audit` | Composer 2.10.1 | **33 advisories across 9 locked vendor packages** (pre-existing lockfile versions; documented below) |
| Migrations + Seeding + Rollback (`step=15`) + Re-migrate | SQLite | **Passed** (all 43 migrations + `redas:reporting-units:sync-zones`) |
| `npm run build` (Vite 7.3.3) | Node 26.13.0 | **Passed** (`✓ built in 2.73s`) |
| New Security, Option C, Export, Reliability & Smoke Suites (14 suites) | **MySQL 8.4.7** (`redas_test`) | **35 passed, 0 failed (211 assertions, 28.04s)** |
| Full Suite (`php artisan test`) | SQLite `:memory:` | **155 passed, 4 pre-existing failures** (see §4 below) |
| Live Browser UI Walkthrough (`http://127.0.0.1:8012`) | Chrome/Playwright via `browser` subagent | **HTTP 200 on all 7 tested flows** (`/login`, `/mfa/challenge`, `/desk-admin/dashboard`, `/prs/dashboard`, `/prs/indicators`, `/consolidated`, `/user/archive`, `/user/reports`); minor pre-existing JS duplicate `notifBtn` declaration observed in `desk-admin/layout.blade.php` |

---

## 4. Pre-Existing Issues & Known Limitations

1. **4 Pre-Existing Baseline Test Failures (unchanged from original codebase):**
   - `AuthLoginTest::test_login_fails_when_role_does_not_match_user_record` — web login intentionally removed the `role` selector per `TODO.md`.
   - `AuthRegistrationTest::test_users_can_register_with_a_supported_role_selection` — `POST /register` returns `405` because self-registration was disabled.
   - `RegistrationFormFieldsTest::test_registration_form_exposes_location_and_access_profile_fields` — `GET /register` redirects to `/login`.
   - `StateCombinedFormTest::test_combined_form_renders_all_ten_directorate_sections` — `name="data_consent"` renders twice when the combined state form embeds `sections/investigation.blade.php`.
2. **Vendor Package Advisories (`composer audit`):** 33 advisories affect 9 locked transitive packages (including `guzzlehttp/guzzle` and `symfony/yaml`). A controlled `composer update` with regression testing is recommended prior to production cutover.
3. **Browser Console Note:** `desk-admin/layout.blade.php` declares `notifBtn` twice in inline scripts; does not break HTTP responses or workflows but should be deduplicated in a UI polish pass.
4. **Organisational Approvals Required Before Production:**
   - Confirm PRS viewer role membership (`directorate_admin` for `prs` + national HQ viewers).
   - Verify official NIS state-to-zone hierarchy after running `redas:reporting-units:sync-zones`.
   - Populate Directorate sections/sub-directorates in `reporting_units`.
   - Review and promote provisional `reporting_indicators` to `approved`.
   - Confirm HQ Admin as final approver for consolidated reports and confirm statutory retention days (`2555` default).
   - Coordinate API mobile client update for 6-digit password-bound OTP challenges and removal of unauthenticated `verify_email` OTP.

---

## 5. Deployment Readiness Statement

**Assessment: Staging-Ready (Not Yet Production-Cleared without Organisational Sign-Off & Dependency Patching).**

All critical security defects, Option C reporting workflows, real archive persistence, real report generation, export sanitization, queued notifications, immutable hash-chained audit logging, and MySQL 8.4.7 integration tests are implemented and verified. Before production go-live, complete the organisational approvals in §4, run a controlled `composer update` to remediate the 33 vendor advisories, and configure production `.env` + `php artisan redas:security:check`.
