<?php

namespace App\Listeners;

use App\Events\SubmissionRejected;
use App\Services\Messaging\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSubmissionRejectedNotification implements ShouldQueue
{
    public function handle(SubmissionRejected $event): void
    {
        $application = $event->application;
        $user = $application->user;

        if (! $user || ! $user->email) {
            return;
        }

        $ref = 'RET-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
        $pathCount = count((array) ($application->workflow_path ?? []));

        // Do not expose private report contents in email; direct the user to the authenticated portal.
        NotificationDispatcher::queueTemplatedEmail(
            user: $user,
            templateKey: 'submission_rejected',
            variables: [
                'name' => $user->name,
                'reference' => $ref,
                'comments' => 'Please sign in to the NIS REDAS portal to review the reviewer feedback.',
                'action_url' => route('user.returns.show', $application),
            ],
            idempotencyKey: "submission:{$application->id}:rejected:{$pathCount}"
        );
    }
}
