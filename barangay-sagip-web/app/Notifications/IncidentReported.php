<?php

namespace App\Notifications;

use App\Models\EmergencyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Feature 8: Emergency email alerts.
 *
 * Sent to officials and to the on-duty responders whose specialization covers a
 * newly filed report. The SOS path uses SosTriggered instead — same recipients,
 * louder wording.
 */
class IncidentReported extends Notification implements ShouldQueue
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
            'category' => $this->emergencyRequest->category,
            'urgency' => $this->emergencyRequest->urgency?->value,
            'message' => sprintf(
                'New %s report #%d (%s urgency) from %s.',
                str_replace('_', ' ', $this->emergencyRequest->category ?? 'unclassified'),
                $this->emergencyRequest->id,
                $this->emergencyRequest->urgency?->value ?? 'unknown',
                $this->emergencyRequest->resident?->name ?? 'a resident',
            ),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->emergencyRequest;
        $resident = $request->resident;

        $mail = (new MailMessage)
            ->subject(sprintf(
                '[SAGIP] New %s report #%d — %s urgency',
                str_replace('_', ' ', $request->category ?? 'unclassified'),
                $request->id,
                $request->urgency?->value ?? 'unknown',
            ))
            ->greeting('New emergency report')
            ->line(sprintf(
                '%s filed a report at %s.',
                $resident?->name ?? 'A resident',
                $request->created_at->format('M j, Y g:i A'),
            ))
            ->line(sprintf('Category: %s', str_replace('_', ' ', $request->category ?? 'unclassified')))
            ->line(sprintf('Urgency: %s', $request->urgency?->label() ?? 'Unknown'))
            ->line(sprintf('Location: %s, %s', $request->latitude, $request->longitude))
            ->line('Report: '.$request->description);

        if ($resident?->phone_number) {
            $mail->line(sprintf('Contact number: %s', $resident->phone_number));
        }

        if ($resident?->residentProfile?->hasVulnerableMembers()) {
            $mail->line(sprintf(
                'Vulnerable household members: %s',
                $resident->residentProfile->vulnerabilityTagLabels(),
            ));
        }

        if ($request->needs_review) {
            $mail->line('This report was flagged for manual review: '.($request->review_reason ?? 'low classification confidence.'));
        }

        return $mail->action('Open the request', route('requests.show', $request));
    }
}
