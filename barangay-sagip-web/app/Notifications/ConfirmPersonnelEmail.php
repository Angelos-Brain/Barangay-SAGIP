<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * First Login, step 4: the link a responder clicks to confirm their email and
 * activate their account. Sent immediately rather than queued — the
 * responder is waiting on the screen for it.
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
            ->subject('[SAGIP] Confirm your email to activate your responder account')
            ->greeting(sprintf('Hi %s,', $notifiable->name))
            ->line('Confirm this email address to finish setting up your Barangay SAGIP responder account.')
            ->action('Confirm Email', $this->confirmationUrl)
            ->line(sprintf('This link works once and expires in %d hours.', $this->expiresInHours))
            ->line('If you did not set up a Barangay SAGIP account, ignore this email and tell your barangay admin.');
    }
}
