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

    /**
     * A submission is locked from submitter edits/deletion once it has received
     * final approval OR has already been approved by a supervisor and forwarded
     * to a higher review tier (unless a reviewer later returns/rejects it).
     */
    private function isLockedByApproval(Application $application): bool
    {
        $status = strtolower((string) $application->status);
        $stage = strtolower((string) $application->workflow_stage);

        if ($status === 'approved' || $stage === 'approved') {
            return true;
        }

        // Returned/rejected submissions may be edited & resubmitted by the officer
        if (in_array($status, ['returned', 'rejected', 'queried'], true)) {
            return false;
        }

        // If the submission is still pending, check if it already passed an approval stage
        $category = strtolower((string) $application->category);
        $advancedStages = match ($category) {
            'state' => ['zonal_review', 'hq_review', 'admin_review', 'approved'],
            default => ['hq_review', 'admin_review', 'approved'],
        };

        if (in_array($stage, $advancedStages, true)) {
            return true;
        }

        // Also check workflow_path for any recorded approval action on the current pending cycle
        $path = is_array($application->workflow_path) ? $application->workflow_path : [];
        for ($i = count($path) - 1; $i >= 0; $i--) {
            $step = $path[$i];
            $stepStage = strtolower((string) ($step['stage'] ?? ''));
            $stepAction = strtolower((string) ($step['action'] ?? ''));
            if (in_array($stepAction, ['rejected', 'returned'], true) || in_array($stepStage, ['submitted', 'resubmitted'], true)) {
                break;
            }
            if ($stepAction === 'approved') {
                return true;
            }
        }

        return false;
    }

    public function update(User $user, Application $application): bool
    {
        return $application->user_id === $user->id
            && ! $this->isLockedByApproval($application);
    }

    public function delete(User $user, Application $application): bool
    {
        if ($application->user_id !== $user->id) {
            return false;
        }

        if ($this->isLockedByApproval($application)) {
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
