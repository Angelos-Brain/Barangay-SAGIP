<?php

namespace App\Notifications;

use App\Models\ResponseAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ResponseAssignment $assignment) {}

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
        $request = $this->assignment->emergencyRequest;

        return [
            'assignment_id' => $this->assignment->id,
            'emergency_request_id' => $request->id,
            'message' => "You have been assigned to request #{$request->id} ({$request->category}, {$request->urgency?->value}).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->assignment->emergencyRequest;

        return (new MailMessage)
            ->subject("New assignment: Request #{$request->id}")
            ->line("You've been assigned to a {$request->urgency?->value} priority {$request->category} request.")
            ->line($request->description)
            ->action('View Request', route('requests.show', $request));
    }
}
