<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportAccess
{
    public static function isNationalViewer(User $user): bool
    {
        return $user->hasCategory('hq_admin', 'admin', 'super_admin')
            && $user->hasLocationType('headquarters');
    }

    public static function isPrsViewer(User $user): bool
    {
        if (self::isNationalViewer($user)) {
            return true;
        }

        return $user->hasCategory('directorate_admin')
            && $user->directorateSlug() === 'prs';
    }

    public static function canManageConsolidated(User $user): bool
    {
        if ($user->user_category === 'zonal_commander') {
            $zone = trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code));

            return $zone !== '';
        }

        if ($user->user_category === 'directorate_admin') {
            return $user->directorateSlug() !== null;
        }

        return false;
    }

    /**
     * Resolve the reviewer's authoritative organisational scope.
     * Returns null when the user has no valid scope (deny-by-default).
     *
     * @return array{0:string,1:?string}|null
     */
    public static function scopeFor(User $user): ?array
    {
        return match ($user->user_category) {
            'desk_admin' => self::nonEmptyScope('scope_code', $user->primary_location_code ?: $user->assigned_state_code),
            'directorate_admin' => self::nonEmptyScope('scope_code', $user->directorateSlug()),
            'cgis_desk_admin' => self::nonEmptyScope('scope_code', $user->cgisUnitSlug()),
            'zonal_commander' => self::nonEmptyScope('zone', $user->primary_location_code ?: $user->assigned_zonal_command_code),
            'hq_admin' => self::isNationalViewer($user) ? ['national', null] : null,
            default => null,
        };
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function nonEmptyScope(string $type, ?string $value): ?array
    {
        $trimmed = $value !== null ? trim($value) : '';

        return $trimmed !== '' ? [$type, $trimmed] : null;
    }

    /**
     * Return active formation codes assigned under a zone in reporting_units (if table exists).
     *
     * @return list<string>
     */
    public static function formationCodesForZone(string $zoneCode): array
    {
        if ($zoneCode === '' || ! Schema::hasTable('reporting_units')) {
            return [];
        }

        $zoneId = DB::table('reporting_units')
            ->where('code', $zoneCode)
            ->where('type', 'zone')
            ->value('id');

        if (! $zoneId) {
            return [];
        }

        return DB::table('reporting_units')
            ->where('parent_id', $zoneId)
            ->where('type', 'formation')
            ->where('is_active', true)
            ->pluck('code')
            ->filter(fn ($c) => is_string($c) && $c !== '')
            ->values()
            ->all();
    }

    /**
     * Restrict an Application query strictly to the reviewer's organisational scope.
     * Deny-by-default: reviewers without a valid scope receive an empty result set.
     */
    public static function applyApproverScope(Builder $query, User $user): Builder
    {
        $scope = self::scopeFor($user);

        if ($scope === null) {
            return $query->whereRaw('1 = 0');
        }

        [$type, $value] = $scope;

        if ($type === 'national') {
            return $query;
        }

        if ($type === 'zone' && is_string($value)) {
            $formationCodes = self::formationCodesForZone($value);

            return $query->where(function (Builder $q) use ($value, $formationCodes): void {
                $q->where('zonal_code', $value)
                    ->orWhere(function (Builder $inner) use ($value): void {
                        $inner->whereNull('zonal_code')
                            ->where('scope_code', $value);
                    });

                if ($formationCodes !== []) {
                    $q->orWhere(function (Builder $inner) use ($formationCodes): void {
                        $inner->where('category', SubmissionWorkflow::CATEGORY_STATE)
                            ->whereIn('scope_code', $formationCodes);
                    });
                }
            });
        }

        return $query->where('scope_code', $value);
    }

    /**
     * Restrict an Application query to records the user is permitted to view
     * (own submissions, in-scope reviewer records, or national/PRS viewer access).
     */
    public static function applyVisibility(Builder $query, User $user): Builder
    {
        if (self::isNationalViewer($user) || self::isPrsViewer($user)) {
            return $query;
        }

        $scope = self::scopeFor($user);

        if ($scope !== null) {
            return $query->where(function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhere(function (Builder $scoped) use ($user): void {
                        self::applyApproverScope($scoped, $user);
                    });
            });
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Whether an Application falls inside the reviewer's organisational scope.
     */
    public static function matchesApproverScope(Application $application, User $user): bool
    {
        $scope = self::scopeFor($user);

        if ($scope === null) {
            return false;
        }

        [$type, $value] = $scope;

        if ($type === 'national') {
            return true;
        }

        if ($type === 'zone' && is_string($value)) {
            if ($application->zonal_code === $value) {
                return true;
            }

            if ($application->zonal_code === null && $application->scope_code === $value) {
                return true;
            }

            if ($application->category === SubmissionWorkflow::CATEGORY_STATE) {
                return in_array($application->scope_code, self::formationCodesForZone($value), true);
            }

            return false;
        }

        return $application->scope_code === $value;
    }

    /**
     * Whether the user is authorized to view or download the given Application.
     */
    public static function canView(Application $application, User $user): bool
    {
        if ($application->user_id === $user->id) {
            return true;
        }

        if (self::isNationalViewer($user) || self::isPrsViewer($user)) {
            return true;
        }

        return self::matchesApproverScope($application, $user);
    }

    /**
     * Whether the user is authorized to approve or return the given Application.
     */
    public static function canReview(Application $application, User $user): bool
    {
        $stage = SubmissionWorkflow::stageForApprover($user);
        if ($stage === null) {
            return false;
        }

        // Prevent self-approval / self-review of one's own submission.
        if ($application->user_id === $user->id) {
            return false;
        }

        if ($application->workflow_stage !== $stage) {
            return false;
        }

        if (in_array(strtolower((string) $application->status), ['approved', 'rejected'], true)) {
            return false;
        }

        return self::matchesApproverScope($application, $user);
    }
}
