<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\DatabaseBackupService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminSettingController extends Controller
{
    /**
     * Display the System Settings & Platform Control Center.
     */
    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');

        $defaults = [
            // Maintenance & Platform Availability
            'maintenance_mode' => false,
            'maintenance_message' => 'NIS-REDAS is currently undergoing scheduled system maintenance. Please check back shortly.',
            'maintenance_eta' => '',
            'system_banner_enabled' => false,
            'system_banner_message' => '',

            // Reporting & Submission Controls
            'submissions_open' => true,
            'submission_deadline_day' => 25,
            'allow_return_resubmission' => true,

            // Security & Authentication Policy
            'mfa_required' => true,
            'session_timeout_minutes' => 120,
            'max_login_attempts' => 5,
            'force_password_change_on_create' => true,

            // Geofencing & ABAC Access Control
            'abac_enabled' => false,
            'abac_location_enforcement' => false,
            'abac_hq_bypass' => true,
            'abac_allowed_countries' => 'Nigeria',
            'geolocation_provider' => 'ipapi_co',
            'default_geofence_radius_km' => 25,
            'geolocation_api_token' => '',
        ];

        $values = [];
        foreach ($defaults as $key => $default) {
            $setting = $settings->get($key);
            $value = $setting?->typedValue() ?? $default;

            if (is_bool($default)) {
                $values[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
            } elseif (is_int($default)) {
                $values[$key] = is_numeric($value) ? (int) $value : $default;
            } else {
                $values[$key] = (string) ($value ?? '');
            }
        }

        return view('admin.settings.index', [
            'settings' => $values,
            'dbInfo' => DatabaseBackupService::databaseInfo(),
            'backups' => DatabaseBackupService::listBackups(),
        ]);
    }

    /**
     * Update global platform settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Maintenance & Platform Availability
            'maintenance_mode' => 'required|boolean',
            'maintenance_message' => 'required|string|max:500',
            'maintenance_eta' => 'nullable|string|max:120',
            'system_banner_enabled' => 'required|boolean',
            'system_banner_message' => 'nullable|string|max:400',

            // Reporting & Submission Controls
            'submissions_open' => 'required|boolean',
            'submission_deadline_day' => 'required|integer|min:1|max:31',
            'allow_return_resubmission' => 'required|boolean',

            // Security & Authentication Policy
            'mfa_required' => 'required|boolean',
            'session_timeout_minutes' => 'required|integer|min:15|max:480',
            'max_login_attempts' => 'required|integer|min:3|max:20',
            'force_password_change_on_create' => 'required|boolean',

            // Geofencing & ABAC Access Control
            'abac_enabled' => 'required|boolean',
            'abac_location_enforcement' => 'required|boolean',
            'abac_hq_bypass' => 'required|boolean',
            'abac_allowed_countries' => 'required|string|max:255',
            'geolocation_provider' => ['required', 'string', Rule::in(['ipapi_co', 'ip_api_pro', 'ipinfo', 'ipapi', 'ip-api'])],
            'default_geofence_radius_km' => 'required|integer|min:1|max:500',
            'geolocation_api_token' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $settings = Setting::all()->keyBy('key');

        $types = [
            'maintenance_mode' => 'boolean',
            'maintenance_message' => 'string',
            'maintenance_eta' => 'string',
            'system_banner_enabled' => 'boolean',
            'system_banner_message' => 'string',
            'submissions_open' => 'boolean',
            'submission_deadline_day' => 'integer',
            'allow_return_resubmission' => 'boolean',
            'mfa_required' => 'boolean',
            'session_timeout_minutes' => 'integer',
            'max_login_attempts' => 'integer',
            'force_password_change_on_create' => 'boolean',
            'abac_enabled' => 'boolean',
            'abac_location_enforcement' => 'boolean',
            'abac_hq_bypass' => 'boolean',
            'abac_allowed_countries' => 'string',
            'geolocation_provider' => 'string',
            'default_geofence_radius_km' => 'integer',
            'geolocation_api_token' => 'string',
        ];

        foreach ($validated as $key => $newValue) {
            $type = $types[$key] ?? 'string';
            if ($type === 'boolean') {
                $newValue = filter_var($newValue, FILTER_VALIDATE_BOOLEAN);
            } elseif ($type === 'integer') {
                $newValue = (int) $newValue;
            } else {
                $newValue = (string) ($newValue ?? '');
            }

            $oldValue = $settings->get($key)?->typedValue();
            if ($oldValue !== $newValue) {
                SettingService::set($key, $newValue, $type);
                AuditLogger::logSettingChange($user, $key, $oldValue, $newValue);
            }
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'System settings saved successfully.');
    }

    /**
     * Create a full backup of the entire application database.
     */
    public function createBackup(Request $request): RedirectResponse
    {
        try {
            $entry = DatabaseBackupService::createBackup($request->user());

            AuditLogger::log(
                user: $request->user(),
                action: 'database.backup.created',
                status: 'success',
                entityType: 'database_backup',
                entityId: null,
                details: [
                    'filename' => $entry['filename'],
                    'driver' => $entry['driver'],
                    'size' => $entry['size_human'],
                ],
                ip: $request->ip()
            );

            return redirect()
                ->route('admin.settings.index')
                ->with('status', "Full database backup created: {$entry['filename']} ({$entry['size_human']}).");
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Database backup failed: '.$e->getMessage());
        }
    }

    /**
     * Download a previously created database backup.
     */
    public function downloadBackup(Request $request, string $filename): BinaryFileResponse
    {
        $path = DatabaseBackupService::safePath($filename);
        abort_if($path === null, 404, 'Backup file not found.');

        AuditLogger::log(
            user: $request->user(),
            action: 'database.backup.downloaded',
            status: 'success',
            entityType: 'database_backup',
            entityId: null,
            details: ['filename' => basename($path)],
            ip: $request->ip()
        );

        return response()->download($path, basename($path));
    }

    /**
     * Delete a stored database backup.
     */
    public function deleteBackup(Request $request, string $filename): RedirectResponse
    {
        $deleted = DatabaseBackupService::deleteBackup($filename);

        if (! $deleted) {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Backup file could not be found or deleted.');
        }

        AuditLogger::log(
            user: $request->user(),
            action: 'database.backup.deleted',
            status: 'success',
            entityType: 'database_backup',
            entityId: null,
            details: ['filename' => basename($filename)],
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Backup deleted: '.basename($filename));
    }

    /**
     * Clear application cache and compiled Blade views.
     */
    public function clearCache(Request $request): RedirectResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');

            AuditLogger::log(
                user: $request->user(),
                action: 'system.cache.cleared',
                status: 'success',
                entityType: 'system',
                entityId: null,
                details: ['cleared' => ['cache', 'views']],
                ip: $request->ip()
            );

            return redirect()
                ->route('admin.settings.index')
                ->with('status', 'Application cache and compiled views cleared successfully.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Failed to clear cache: '.$e->getMessage());
        }
    }
}
