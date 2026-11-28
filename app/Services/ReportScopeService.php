<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authoritative role-based scoping for Report Builder preview & exports.
 *
 * - PRS / HQ Admin / Super Admin: National (all Zones, States, Formations, Directorates, CGIS Units)
 * - Zonal Admin / Zonal Officer: Their Zone + all subordinate State Commands / Formations under that Zone
 * - State Desk Admin / State Officer: Only their assigned State Command / Formation
 * - Directorate Admin / Directorate Officer (non-PRS): Only their Directorate and its sections
 * - CGIS Desk Admin / CGIS Unit Officer: Only their assigned CGIS Unit / Office
 */
class ReportScopeService
{
    public const DIRECTORATES = [
        'hrm' => 'Human Resources Management (HRM)',
        'prs' => 'Planning, Research & Statistics (PRS)',
        'finance' => 'Finance & Accounts (F/A)',
        'investigation' => 'Investigation & Compliance (I/C)',
        'passport' => 'Passport & Travel Documents (P/OTD)',
        'visa' => 'Visa & Residency (V/R)',
        'migration' => 'Migration Directorate',
        'border' => 'Border Management',
        'ict' => 'ICT / Cybersecurity',
        'works-logistics' => 'Works & Logistics',
    ];

    public const CGIS_UNITS = [
        'actu' => 'ACTU (Anti-Corruption & Transparency)',
        'epms' => 'EPMS (Performance Management)',
        'hostmanship' => 'Hostmanship Unit',
        'pro-media' => 'PRO / Public Relations & Media',
        'protocol' => 'Protocol Unit',
        'provost' => 'Provost Unit',
        'servicom' => 'SERVICOM Unit',
    ];

    public static function scopeLevel(User $user): string
    {
        if (ReportAccess::isNationalViewer($user)
            || ReportAccess::isPrsViewer($user)
            || in_array($user->user_category, ['super_admin', 'hq_admin', 'admin'], true)
            || $user->directorateSlug() === 'prs') {
            return 'national';
        }

        if (in_array($user->user_category, ['zonal_commander', 'zonal_officer'], true)) {
            return 'zone';
        }

        if (in_array($user->user_category, ['directorate_admin', 'directorate_user'], true)) {
            return 'directorate';
        }

        if (in_array($user->user_category, ['cgis_desk_admin', 'cgis_unit_user'], true)) {
            return 'cgis';
        }

        return 'state';
    }

    /**
     * All command/formation codes belonging to a Zone (from config + reporting_units table).
     *
     * @return list<string>
     */
    public static function commandCodesForZone(string $zoneCode): array
    {
        $zoneCode = strtoupper(trim($zoneCode));
        if ($zoneCode === '') {
            return [];
        }

        $configCmds = array_keys((array) config("nis_hierarchy.zones.{$zoneCode}.commands", []));
        $dbCmds = ReportAccess::formationCodesForZone($zoneCode);

        return array_values(array_unique(array_map(
            static fn ($c) => strtoupper(trim((string) $c)),
            array_merge($configCmds, $dbCmds)
        )));
    }

    /**
     * Enforce strict organizational scope on an Application query (deny-by-default).
     */
    public static function applyRoleScope(Builder $query, User $user): Builder
    {
        $level = self::scopeLevel($user);

        if ($level === 'national') {
            return $query;
        }

        if ($level === 'zone') {
            $zoneCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code)));
            if ($zoneCode === '') {
                return $query->whereRaw('1 = 0');
            }

            $childCommands = self::commandCodesForZone($zoneCode);
            $allowedScopes = array_values(array_unique(array_merge([$zoneCode], $childCommands)));

            return $query->where(function (Builder $q) use ($zoneCode, $allowedScopes): void {
                $q->where('zonal_code', $zoneCode)
                    ->orWhereIn('scope_code', $allowedScopes)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('assigned_zonal_command_code', $zoneCode));
            })->whereIn('category', [SubmissionWorkflow::CATEGORY_STATE, SubmissionWorkflow::CATEGORY_ZONE]);
        }

        if ($level === 'directorate') {
            $dirSlug = strtolower(trim((string) ($user->directorateSlug() ?: $user->assigned_directorate_code)));
            if ($dirSlug === '') {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
                ->whereRaw('LOWER(scope_code) = ?', [$dirSlug]);
        }

        if ($level === 'cgis') {
            $unitSlug = strtolower(trim((string) ($user->cgisUnitSlug() ?: $user->assigned_cgis_unit_code)));
            if ($unitSlug === '') {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('category', [SubmissionWorkflow::CATEGORY_CGIS, 'cgis_unit'])
                ->whereRaw('LOWER(scope_code) = ?', [$unitSlug]);
        }

        // State / Formation officer or Desk Admin: strictly locked to their State Command
        $stateCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_state_code)));
        if ($stateCode === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('category', SubmissionWorkflow::CATEGORY_STATE)
            ->whereRaw('UPPER(scope_code) = ?', [$stateCode]);
    }

    /**
     * Apply user-selected filters strictly within the user's authorized role scope.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyFilters(Builder $query, User $user, array $filters): Builder
    {
        self::applyRoleScope($query, $user);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['date_to']);
        }

        $status = strtolower(trim((string) ($filters['status_filter'] ?? 'all')));
        if (in_array($status, ['approved', 'pending', 'returned'], true)) {
            $query->where('status', $status);
        }

        $level = self::scopeLevel($user);

        if ($level === 'national') {
            $cat = strtolower(trim((string) ($filters['category_filter'] ?? '')));
            if ($cat === 'state') {
                $query->where('category', SubmissionWorkflow::CATEGORY_STATE)
                    ->where('scope_code', 'not like', 'ZONE-%');
            } elseif ($cat === 'zone') {
                $query->where(function (Builder $q): void {
                    $q->where('category', SubmissionWorkflow::CATEGORY_ZONE)
                        ->orWhere('scope_code', 'like', 'ZONE-%');
                });
            } elseif ($cat === 'directorate') {
                $query->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE);
            } elseif ($cat === 'cgis') {
                $query->whereIn('category', [SubmissionWorkflow::CATEGORY_CGIS, 'cgis_unit']);
            }

            if (! empty($filters['command_code'])) {
                $cmd = strtoupper(trim((string) $filters['command_code']));
                $query->whereRaw('UPPER(scope_code) = ?', [$cmd]);
            } elseif (! empty($filters['directorate_code'])) {
                $dir = self::normalizeDirectorateSlug((string) $filters['directorate_code']);
                $query->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
                    ->whereRaw('LOWER(scope_code) = ?', [$dir]);
            } elseif (! empty($filters['cgis_unit_code'])) {
                $unit = strtolower(trim((string) $filters['cgis_unit_code']));
                $query->whereIn('category', [SubmissionWorkflow::CATEGORY_CGIS, 'cgis_unit'])
                    ->whereRaw('LOWER(scope_code) = ?', [$unit]);
            } elseif (! empty($filters['zone_code'])) {
                $zoneCode = strtoupper(trim((string) $filters['zone_code']));
                $allZoneCodes = array_values(array_unique(array_merge([$zoneCode], self::commandCodesForZone($zoneCode))));
                $query->where(function (Builder $q) use ($zoneCode, $allZoneCodes): void {
                    $q->where('zonal_code', $zoneCode)
                        ->orWhereIn('scope_code', $allZoneCodes);
                });
            }
        } elseif ($level === 'zone' && ! empty($filters['command_code'])) {
            $zoneCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code)));
            $allowed = array_values(array_unique(array_merge([$zoneCode], self::commandCodesForZone($zoneCode))));
            $requested = strtoupper(trim((string) $filters['command_code']));
            if (in_array($requested, $allowed, true)) {
                $query->whereRaw('UPPER(scope_code) = ?', [$requested]);
            }
        } elseif ($level === 'directorate' && ! empty($filters['section_code'])) {
            $sec = trim((string) $filters['section_code']);
            $query->where('section_code', $sec);
        }

        return $query;
    }

    public static function normalizeDirectorateSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return match ($slug) {
            'ic' => 'investigation',
            'works' => 'works-logistics',
            default => $slug,
        };
    }

    /**
     * Distinct section codes submitted under a specific directorate (for Directorate section filter).
     *
     * @return list<string>
     */
    public static function sectionsForDirectorate(string $dirSlug): array
    {
        $dirSlug = self::normalizeDirectorateSlug($dirSlug);
        if ($dirSlug === '') {
            return [];
        }

        return Application::query()
            ->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
            ->whereRaw('LOWER(scope_code) = ?', [$dirSlug])
            ->whereNotNull('section_code')
            ->where('section_code', '!=', '')
            ->distinct()
            ->orderBy('section_code')
            ->pluck('section_code')
            ->values()
            ->all();
    }
}
