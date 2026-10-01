<?php

namespace App\Services;

use App\Enums\Specialization;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Feature 8: Emergency email alerts.
 *
 * Resolves who must hear about an incident and sends them the alert. Officials
 * and administrators always do; field responders do when they are on duty AND
 * one of their specialization tags (Feature 10) covers the incident's category.
 *
 * The recipient rule lives here once, so the SOS path and the ordinary report
 * path cannot drift apart.
 */
class IncidentAlertService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Send the alert and record who it went to.
     */
    public function alert(EmergencyRequest $request, BaseNotification $notification): int
    {
        $recipients = $this->recipientsFor($request);

        if ($recipients->isEmpty()) {
            $this->auditLogger->record(
                action: 'incident.alert_no_recipients',
                subject: $request,
                description: sprintf(
                    'No official or on-duty %s responder was available to alert about request #%d.',
                    $request->category ?? 'unclassified',
                    $request->id,
                ),
            );

            return 0;
        }

        Notification::send($recipients, $notification);

        $this->auditLogger->record(
            action: 'incident.alerted',
            subject: $request,
            after: [
                'recipient_count' => $recipients->count(),
                'recipient_ids' => $recipients->pluck('id')->all(),
                'category' => $request->category,
            ],
            description: sprintf(
                'Alerted %d recipient(s) about request #%d (%s).',
                $recipients->count(),
                $request->id,
                $request->category ?? 'unclassified',
            ),
        );

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(EmergencyRequest $request): Collection
    {
        $responderUserIds = $this->onDutyResponderUserIdsFor($request);

        return User::query()
            ->where(function ($query) use ($responderUserIds) {
                $query->whereIn('role', [UserRole::Official->value, UserRole::Admin->value])
                    ->orWhereIn('id', $responderUserIds);
            })
            // The reporter is told about their own request through the status
            // notification; they must not also receive the responder alert.
            ->whereKeyNot($request->resident_id)
            ->get();
    }

    /**
     * Users behind an on-duty responder record whose specialization tags cover
     * this incident's category.
     *
     * @return list<int>
     */
    public function onDutyResponderUserIdsFor(EmergencyRequest $request): array
    {
        $wanted = array_map(
            fn (Specialization $specialization) => $specialization->value,
            Specialization::forIncidentCategory($request->category),
        );

        return ResponsePersonnel::query()
            ->whereNotNull('user_id')
            ->where('is_available', true)
            ->get()
            ->filter(fn (ResponsePersonnel $personnel) => array_intersect(
                $personnel->specializationValues(),
                $wanted,
            ) !== [])
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }
}
