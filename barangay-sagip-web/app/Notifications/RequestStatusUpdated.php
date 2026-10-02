<?php

namespace App\Notifications;

use App\Models\EmergencyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Feature 10: Alerts and Notifications.
 *
 * Sent to the resident whenever their request's status changes (submitted,
 * validated, assigned, en route, resolved, etc.). Uses Laravel's built-in
 * database notifications channel so it shows up in the in-app notification
 * list (Feature 10) without requiring SMS/mail setup to demo; add 'mail' or
 * a custom 'sms' channel to `via()` once those are configured.
 */
class RequestStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $forResponder  word the message for the assigned responder
     *                              rather than the resident who filed it
     */
    public function __construct(
        public EmergencyRequest $emergencyRequest,
        public string $newStatus,
        public bool $forResponder = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The in-app copy is written immediately so it never waits on a queue
     * worker; any email still goes through the queue.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'emergency_request_id' => $this->emergencyRequest->id,
            'status' => $this->newStatus,
            'message' => sprintf(
                $this->forResponder ? 'Request #%d assigned to you is now: %s' : 'Your request #%d is now: %s',
                $this->emergencyRequest->id,
                ucfirst(str_replace('_', ' ', $this->newStatus)),
            ),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Barangay SAGIP: Request #{$this->emergencyRequest->id} update")
            ->line('Your request status is now: '.ucfirst(str_replace('_', ' ', $this->newStatus)))
            ->action('View Request', route('requests.show', $this->emergencyRequest));
    }
}
