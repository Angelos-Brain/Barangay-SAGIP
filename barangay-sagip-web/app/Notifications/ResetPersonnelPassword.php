<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Forgot password for response personnel: the link that lets a responder
 * choose a new password. Sent immediately rather than queued.
 */
class ResetPersonnelPassword extends Notification
{
    public function __construct(public string $resetUrl, public int $expiresInHours) {}

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
            ->subject('[SAGIP] Reset your responder account password')
            ->greeting(sprintf('Hi %s,', $notifiable->name))
            ->line('We received a request to reset the password for your Barangay SAGIP responder account.')
            ->action('Choose a New Password', $this->resetUrl)
            ->line(sprintf('This link works once and expires in %d hours.', $this->expiresInHours))
            ->line('If you did not ask for this, ignore this email — your password stays the same.');
    }
}
