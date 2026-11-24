<?php

namespace App\Services\Messaging;

use App\Jobs\SendTemplatedEmailJob;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\QueryException;

class NotificationDispatcher
{
    /**
     * Queue a templated email with idempotency protection so duplicate events
     * never send duplicate notifications.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function queueTemplatedEmail(
        User $user,
        string $templateKey,
        array $variables,
        string $idempotencyKey
    ): NotificationDelivery {
        try {
            $delivery = NotificationDelivery::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'user_id' => $user->id,
                    'channel' => 'email',
                    'template_key' => $templateKey,
                    'status' => 'pending',
                    'attempts' => 0,
                    'provider' => 'resend',
                ]
            );
        } catch (QueryException) {
            $delivery = NotificationDelivery::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }

        if (in_array($delivery->status, ['accepted', 'processing'], true)) {
            return $delivery;
        }

        if ($delivery->wasRecentlyCreated || $delivery->status === 'failed') {
            $delivery->update(['status' => 'pending', 'last_error' => null]);
            SendTemplatedEmailJob::dispatch($delivery->id, $variables)->afterCommit();
        }

        return $delivery;
    }
}
