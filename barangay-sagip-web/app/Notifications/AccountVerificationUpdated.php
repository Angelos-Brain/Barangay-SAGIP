<?php

namespace App\Notifications;

use App\Enums\VerificationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Feature 1 / Feature 10: tells a resident the outcome of their account
 * review — verified, rejected, or sent back to pending.
 */
class AccountVerificationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VerificationStatus $status, public ?string $note = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * The in-app copy is written immediately so it never waits on a queue
     * worker; only the email goes through the queue.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'verification_status' => $this->status->value,
            'note' => $this->note,
            'url' => $this->status === VerificationStatus::Verified
                ? route('dashboard')
                : route('account.verification.pending'),
            'message' => $this->message(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[SAGIP] '.$this->subject())
            ->greeting(sprintf('Hi %s,', $notifiable->name))
            ->line($this->message());

        return $mail->action(
            $this->status === VerificationStatus::Verified ? 'Open Barangay SAGIP' : 'View account status',
            $this->status === VerificationStatus::Verified ? route('dashboard') : route('account.verification.pending'),
        );
    }

    protected function subject(): string
    {
        return match ($this->status) {
            VerificationStatus::Verified => 'Your account has been verified',
            VerificationStatus::Rejected => 'Your account was not approved',
            VerificationStatus::Pending => 'Your account is back under review',
        };
    }

    protected function message(): string
    {
        $message = match ($this->status) {
            VerificationStatus::Verified => 'Your account has been verified. You can now send emergency reports and use the SOS button.',
            VerificationStatus::Rejected => 'Your account was not approved, so emergency reporting is unavailable. Please visit the barangay hall.',
            VerificationStatus::Pending => 'Your account has been returned to pending review by a barangay official.',
        };

        return filled($this->note) ? $message.' Note from the official: '.$this->note : $message;
    }
}
