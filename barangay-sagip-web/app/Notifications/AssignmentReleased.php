<?php

namespace App\Notifications;

use App\Models\EmergencyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Feature 6 / Feature 10: tells a responder an official reassigned the
 * request they were handling, so they stand down.
 */
class AssignmentReleased extends Notification
{
    use Queueable;

    public function __construct(public EmergencyRequest $emergencyRequest) {}

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
            'emergency_request_id' => $this->emergencyRequest->id,
            'message' => sprintf(
                'Request #%d was reassigned to another responder. You no longer need to respond to it.',
                $this->emergencyRequest->id,
            ),
        ];
    }
}
