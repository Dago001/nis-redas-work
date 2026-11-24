<?php

namespace Tests\Feature;

use App\Jobs\SendTemplatedEmailJob;
use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Messaging\NotificationDispatcher;
use App\Services\Messaging\ResendMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class NotificationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedTemplate(): void
    {
        EmailTemplate::query()->create([
            'key' => 'submission_submitted',
            'type' => 'workflow',
            'subject' => 'Return {{ reference }} received',
            'body' => 'Hello {{ name }}, view {{ action_url }}',
            'is_active' => true,
        ]);
    }

    public function test_idempotent_dispatcher_prevents_duplicate_sends_and_marks_status_accepted(): void
    {
        $this->seedTemplate();
        Config::set('services.resend.key', 're_test_key');
        Mail::fake();

        $user = User::factory()->create();

        $first = NotificationDispatcher::queueTemplatedEmail(
            $user,
            'submission_submitted',
            ['name' => $user->name, 'reference' => 'RET-00001', 'action_url' => 'https://example.test/r/1'],
            'submission:1:created'
        );

        // Duplicate dispatch with identical key must not create a second row or resend
        $second = NotificationDispatcher::queueTemplatedEmail(
            $user,
            'submission_submitted',
            ['name' => $user->name, 'reference' => 'RET-00001', 'action_url' => 'https://example.test/r/1'],
            'submission:1:created'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('notification_deliveries', 1);

        $fresh = $first->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertNotSame('delivered', $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->accepted_at);
    }

    public function test_failed_delivery_increments_attempts_marks_failed_and_retries_once(): void
    {
        $this->seedTemplate();
        Config::set('services.resend.key', null); // Force ResendMailService to throw

        $user = User::factory()->create();

        $delivery = NotificationDelivery::create([
            'idempotency_key' => 'submission:99:created',
            'user_id' => $user->id,
            'channel' => 'email',
            'template_key' => 'submission_submitted',
            'status' => 'pending',
            'attempts' => 0,
            'provider' => 'resend',
        ]);

        $job = new SendTemplatedEmailJob($delivery->id, [
            'name' => $user->name,
            'reference' => 'RET-00099',
            'action_url' => 'https://example.test/r/99',
        ]);

        try {
            $job->handle(new ResendMailService());
            $this->fail('Expected RuntimeException when RESEND_API_KEY is unset');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->failed_at);
        $this->assertNotNull($delivery->last_error);

        // Configure key and retry via dispatcher -> transitions to accepted on attempt 2
        Config::set('services.resend.key', 're_test_key');
        Mail::fake();

        NotificationDispatcher::queueTemplatedEmail(
            $user,
            'submission_submitted',
            ['name' => $user->name, 'reference' => 'RET-00099', 'action_url' => 'https://example.test/r/99'],
            'submission:99:created'
        );

        $delivery->refresh();
        $this->assertSame('accepted', $delivery->status);
        $this->assertSame(2, $delivery->attempts);
    }
}
