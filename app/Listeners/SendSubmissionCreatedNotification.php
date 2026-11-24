<?php

namespace App\Listeners;

use App\Events\SubmissionCreated;
use App\Services\Messaging\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSubmissionCreatedNotification implements ShouldQueue
{
    public function handle(SubmissionCreated $event): void
    {
        $application = $event->application;
        $user = $application->user;

        if (! $user || ! $user->email) {
            return;
        }

        $ref = 'RET-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);

        NotificationDispatcher::queueTemplatedEmail(
            user: $user,
            templateKey: 'submission_submitted',
            variables: [
                'name' => $user->name,
                'reference' => $ref,
                'stage' => $application->workflow_stage,
                'action_url' => route('user.returns.show', $application),
            ],
            idempotencyKey: "submission:{$application->id}:created"
        );
    }
}
