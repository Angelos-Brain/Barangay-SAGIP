<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * First Login: the link a responder opens to verify their email. It signs
 * them in to choose their password, which activates the account. Sent when an
 * official adds the responder and again on request; immediately rather than
 * queued, since the responder may be waiting on the screen for it.
 */
class ConfirmPersonnelEmail extends Notification
{
    public function __construct(public string $confirmationUrl, public int $expiresInHours) {}

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
            ->subject('[SAGIP] Verify your email to set up your responder account')
            ->greeting(sprintf('Hi %s,', $notifiable->name))
            ->line('Your barangay added you as Barangay SAGIP response personnel. Verify this email address, then choose your password to activate your account.')
            ->action('Verify Email and Set Up Account', $this->confirmationUrl)
            ->line(sprintf('This link works once and expires in %d hours.', $this->expiresInHours))
            ->line('If you did not set up a Barangay SAGIP account, ignore this email and tell your barangay admin.');
    }
}
