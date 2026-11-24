# NIS REDAS Deployment, Operations, Backup & Recovery

## 1. Pre-Deployment Checklist

1. Configure production `.env` from `.env.example`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`
   - Strong `APP_KEY` and `JWT_SECRET` (`base64:` + 48 random bytes)
   - `DB_CONNECTION=mysql` with strict mode enabled
   - `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
   - `GEOLOCATION_ALLOW_PRIVATE_FALLBACK=false`, `GEOLOCATION_UNKNOWN_POLICY=deny`
   - `TRUSTED_PROXIES=<reverse-proxy-ip-or-cidr>` (never `*`)
   - `RESEND_API_KEY=<production-key>`
2. Run pre-flight security check:
   ```bash
   php artisan redas:security:check
   ```
3. Back up the existing production database and `storage/app/private` before running migrations.

## 2. Deployment Steps

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=ReportingUnitSeeder --force
php artisan redas:reporting-units:sync-zones
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link   # Public static assets only; confidential files stay in storage/app/private
```

### Registering Additional Formations or Directorate Sections
```bash
php artisan redas:reporting-units:add MMIA "Murtala Muhammed Airport Command" formation --parent=ZONE-A --formation-type=airport_command
php artisan redas:reporting-units:add hrm-recruit "HRM Recruitment Section" section --parent=hrm
php artisan redas:reporting-units:assign LA ZONE-A
```

## 3. Rollback Procedure

All remediation migrations are additive and reversible:
```bash
php artisan down
php artisan migrate:rollback --step=15 --force
# Restore pre-deployment database snapshot if required
php artisan up
```

## 4. Queue Workers & Scheduler

Run persistent queue workers under Supervisor (Linux) or NSSM (Windows):
```bash
php artisan queue:work database --sleep=3 --tries=5 --backoff=30,120,600,1800 --max-time=3600
```

Monitor and retry failed jobs:
```bash
php artisan queue:failed
php artisan queue:retry all
```

Schedule Laravel's task runner every minute:
```cron
* * * * * cd /path/to/nis-redas && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Backup & Continuity Procedure (ISO 22301 Principles)

1. **Database Backups:** Take daily encrypted `mysqldump --single-transaction --routines --triggers` snapshots and replicate offsite.
2. **Private Document Backups:** Synchronize `storage/app/private` (`supporting-documents/`, `archive/`, `reports/`) daily alongside database backups so `archive_documents.sha256` matches stored files.
3. **Audit Verification:** Run `php artisan redas:audit:verify` daily and archive `storage/logs/audit-*.log` to centralized read-only log storage.
4. **Restore Drill:** Periodically restore both DB dump and `storage/app/private` into an isolated staging environment, run `php artisan redas:security:check` and `php artisan redas:audit:verify`, and verify sample archive downloads pass SHA-256 verification.
