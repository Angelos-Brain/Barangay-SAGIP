<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link a new resident opens to verify their Gmail address. Until they
 * do, the account cannot sign in. Sent immediately rather than queued — the
 * resident is waiting on the "Check your Gmail" screen for it.
 */
class VerifyResidentEmail extends Notification
{
    public function __construct(public string $verificationUrl, public int $expiresInHours) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[SAGIP] Verify your email to activate your account')
            ->greeting(sprintf('Hi %s,', $notifiable->name))
            ->line('Thanks for registering with Barangay SAGIP. Verify this email address to activate your account.')
            ->action('Verify Email', $this->verificationUrl)
            ->line(sprintf('This link works once and expires in %d hours.', $this->expiresInHours))
            ->line('If you did not create a Barangay SAGIP account, you can ignore this email.');
    }
}
