<?php

namespace App\Listeners;

use App\Events\SubmissionStageAdvanced;
use App\Services\Messaging\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSubmissionAdvancedNotification implements ShouldQueue
{
    public function handle(SubmissionStageAdvanced $event): void
    {
        $application = $event->application;
        $user = $application->user;

        if (! $user || ! $user->email) {
            return;
        }

        $ref = 'RET-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
        $pathCount = count((array) ($application->workflow_path ?? []));

        NotificationDispatcher::queueTemplatedEmail(
            user: $user,
            templateKey: 'submission_advanced',
            variables: [
                'name' => $user->name,
                'reference' => $ref,
                'stage' => $event->newStage,
                'action_url' => route('user.returns.show', $application),
            ],
            idempotencyKey: "submission:{$application->id}:advanced:{$event->newStage}:{$pathCount}"
        );
    }
}
