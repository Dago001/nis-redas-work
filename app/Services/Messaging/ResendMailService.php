<?php

namespace App\Services\Messaging;

use App\Models\EmailTemplate;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResendMailService
{
    /**
     * Send an email from a DB template via the configured Resend mailer.
     * Returns the provider message ID when accepted, or null if not exposed by the transport.
     */
    public function sendFromTemplate(
        string $templateKey,
        array $variables,
        string $toEmail,
        ?string $toName = null
    ): ?string {
        /** @var EmailTemplate|null $tpl */
        $tpl = EmailTemplate::query()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $tpl) {
            throw new \RuntimeException("Email template not found or inactive: {$templateKey}");
        }

        $renderer = new TemplateRenderer();
        $subject = $renderer->render((string) $tpl->subject, $variables);
        $bodyHtml = $renderer->render((string) $tpl->body, $variables);

        $resendApiKey = config('services.resend.key');
        if (! is_string($resendApiKey) || trim($resendApiKey) === '') {
            throw new \RuntimeException('RESEND_API_KEY is not configured. Set it in your environment and clear config cache.');
        }

        try {
            $sent = Mail::mailer('resend')
                ->send([], [], function ($message) use ($toEmail, $toName, $subject, $bodyHtml): void {
                    $message->to($toEmail, $toName);
                    $message->subject($subject);
                    $message->html($bodyHtml);
                });

            if ($sent instanceof SentMessage) {
                return $sent->getMessageId();
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Resend email delivery failed', [
                'template' => $templateKey,
                'recipient_hash' => substr(hash('sha256', strtolower(trim($toEmail))), 0, 16),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to send email for template [{$templateKey}]: {$e->getMessage()}", previous: $e);
        }
    }
}
