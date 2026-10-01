<?php

namespace App\Services;

use App\Enums\IncidentOutcome;
use App\Models\EmergencyRequest;
use App\Models\User;

/**
 * Feature 2: post-incident validation.
 *
 * Records whether an incident was real, and writes one audit entry carrying
 * everything needed to judge it later: the outcome, the reason the resident
 * gave (with their own words for "Other"), the reporter's trust context, and
 * whether this outcome tipped the account into admin review.
 */
class IncidentOutcomeService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function record(EmergencyRequest $request, IncidentOutcome $outcome, User $actor): void
    {
        $previous = $request->outcome;

        $request->update([
            'outcome' => $outcome,
            'outcome_set_by' => $actor->id,
            'outcome_set_at' => now(),
        ]);

        $reporter = $request->resident;
        $falseAlarmCount = $reporter?->falseAlarmCount() ?? 0;

        $this->auditLogger->record(
            action: 'incident.outcome_set',
            subject: $request,
            before: ['outcome' => $previous?->value],
            after: [
                'outcome' => $outcome->value,
                'source' => $request->source,
                'reason' => $request->sos_reason?->value,
                'reason_other' => $request->sos_reason_other,
                'category' => $request->category,
                'location_flags' => $request->location_flags ?? [],
                'reporter_id' => $reporter?->id,
                'reporter_verification_status' => $request->reporter_verification_status?->value,
                'reporter_false_alarm_count' => $falseAlarmCount,
                'reporter_flagged_for_review' => $falseAlarmCount >= User::falseAlarmFlagThreshold(),
            ],
            description: sprintf(
                '%s marked request #%d as %s%s.',
                $actor->name,
                $request->id,
                $outcome->label(),
                $request->sosReasonLabel() === null ? '' : sprintf(' (reason: %s)', $request->sosReasonLabel()),
            ),
            actor: $actor,
        );
    }
}
