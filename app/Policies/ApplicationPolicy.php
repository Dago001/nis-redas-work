<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use App\Services\ReportAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return ReportAccess::canView($application, $user);
    }

    public function download(User $user, Application $application): bool
    {
        return ReportAccess::canView($application, $user);
    }

    public function review(User $user, Application $application): bool
    {
        return ReportAccess::canReview($application, $user);
    }

    public function update(User $user, Application $application): bool
    {
        return $application->user_id === $user->id
            && strtolower((string) $application->status) !== 'approved';
    }

    public function delete(User $user, Application $application): bool
    {
        if ($application->user_id !== $user->id) {
            return false;
        }

        if (strtolower((string) $application->status) === 'approved') {
            return false;
        }

        if (Schema::hasTable('consolidated_report_sources')) {
            $referenced = DB::table('consolidated_report_sources')
                ->where('application_id', $application->id)
                ->exists();
            if ($referenced) {
                return false;
            }
        }

        return true;
    }
}
