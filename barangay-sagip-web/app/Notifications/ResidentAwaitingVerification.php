<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Feature 1 / Feature 10: tells officials and admins a newly registered
 * resident is waiting in the verification queue. Sent synchronously — it
 * is database-only, so there is nothing slow to queue.
 */
class ResidentAwaitingVerification extends Notification
{
    use Queueable;

    public function __construct(public User $resident) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'resident_id' => $this->resident->id,
            'url' => route('verifications.index'),
            'message' => sprintf('New resident account from %s is awaiting verification.', $this->resident->name),
        ];
    }
}
