<?php

namespace App\Notifications;

use App\Models\EmergencyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Feature 2: in-app alert for a raised SOS.
 * Feature 8: the same notification carries the email to officials and to the
 * on-duty responders whose specialization matches the incident.
 */
class SosTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EmergencyRequest $emergencyRequest) {}

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
     * worker; any email still goes through the queue.
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
            'emergency_request_id' => $this->emergencyRequest->id,
            'source' => $this->emergencyRequest->source,
            'latitude' => (string) $this->emergencyRequest->latitude,
            'longitude' => (string) $this->emergencyRequest->longitude,
            'reason' => $this->emergencyRequest->sos_reason?->value,
            'message' => sprintf(
                'SOS from %s (%s) — request #%d needs immediate triage.',
                $this->emergencyRequest->resident?->name ?? 'a resident',
                $this->emergencyRequest->sosReasonLabel() ?? 'reason not given',
                $this->emergencyRequest->id,
            ),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->emergencyRequest;
        $resident = $request->resident;

        $mail = (new MailMessage)
            ->subject(sprintf('[SAGIP SOS] Request #%d needs immediate response', $request->id))
            ->greeting('Emergency alert')
            ->line(sprintf(
                '%s raised an SOS at %s.',
                $resident?->name ?? 'A resident',
                $request->created_at->format('M j, Y g:i A'),
            ))
            ->line(sprintf('Reason: %s', $request->sosReasonLabel() ?? 'not given'))
            ->line(sprintf('Location: %s, %s', $request->latitude, $request->longitude))
            ->line(sprintf('Category: %s', $request->category ?? 'unclassified — triage on arrival'));

        if ($resident?->phone_number) {
            $mail->line(sprintf('Contact number: %s', $resident->phone_number));
        }

        if ($resident?->residentProfile?->hasVulnerableMembers()) {
            $mail->line(sprintf(
                'Vulnerable household members: %s',
                $resident->residentProfile->vulnerabilityTagLabels(),
            ));
        }

        return $mail
            ->action('Open the request', route('requests.show', $request))
            ->line('Respond through Barangay SAGIP so the status trail stays accurate.');
    }
}
