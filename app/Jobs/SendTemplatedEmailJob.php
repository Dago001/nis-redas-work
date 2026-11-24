<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\ResendMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTemplatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Bounded exponential backoff in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly int $deliveryId,
        public readonly array $variables = []
    ) {}

    public function handle(ResendMailService $mailer): void
    {
        /** @var NotificationDelivery|null $delivery */
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $row = NotificationDelivery::query()->whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $row || $row->status === 'accepted') {
                return null;
            }

            $row->attempts = $row->attempts + 1;
            $row->status = 'processing';
            $row->save();

            return $row;
        });

        if (! $delivery) {
            return;
        }

        /** @var User|null $user */
        $user = $delivery->user_id ? User::query()->find($delivery->user_id) : null;
        if (! $user || ! $user->email) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'Recipient email unavailable.']);

            return;
        }

        $templateActive = EmailTemplate::query()
            ->where('key', $delivery->template_key)
            ->where('is_active', true)
            ->exists();

        if (! $templateActive) {
            Log::warning('Skipped templated email notification: template missing or inactive.', [
                'delivery_id' => $delivery->id,
                'template_key' => $delivery->template_key,
            ]);
            $delivery->update(['status' => 'skipped', 'last_error' => 'Template missing or inactive.']);

            return;
        }

        try {
            $messageId = $mailer->sendFromTemplate(
                $delivery->template_key,
                $this->variables,
                $user->email,
                $user->name
            );

            // Record provider acceptance — never falsely report final inbox delivery.
            $delivery->update([
                'status' => 'accepted',
                'provider' => 'resend',
                'provider_message_id' => $messageId,
                'provider_response' => ['accepted' => true, 'message_id' => $messageId],
                'accepted_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'status' => 'pending',
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if ($delivery) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);
        }

        Log::error('Templated email notification job failed permanently', [
            'delivery_id' => $this->deliveryId,
            'template_key' => $delivery?->template_key,
            'error' => $e->getMessage(),
        ]);

        AuditLogger::log(
            user: null,
            action: 'notification.failed',
            status: 'failure',
            entityType: 'notification_delivery',
            entityId: $this->deliveryId,
            details: [
                'template_key' => $delivery?->template_key,
                'attempts' => $delivery?->attempts,
            ]
        );
    }
}
