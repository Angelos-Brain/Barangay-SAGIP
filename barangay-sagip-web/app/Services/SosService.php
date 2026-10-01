<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\SosReason;
use App\Enums\UrgencyLevel;
use App\Models\EmergencyRequest;
use App\Models\OutboundSmsMessage;
use App\Models\User;
use App\Notifications\SosTriggered;
use App\Services\Sms\SmsDispatcher;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Feature 2: SOS button.
 *
 * An SOS is deliberately NOT sent through the tokenization classifier: the
 * classifier call is a network round-trip on the most time-critical path in the
 * application. Every SOS is filed as critical and flagged for review; the
 * resident's chosen reason sets the incident category directly, so responders
 * are routed by specialization exactly as for a classified report ("Other"
 * alerts every specialization).
 *
 * Corroborating data (GPS accuracy, device id, the reporter's verification
 * status) is attached to every SOS, and questionable locations are flagged —
 * never blocked.
 */
class SosService
{
    public function __construct(
        protected SmsDispatcher $smsDispatcher,
        protected AuditLogger $auditLogger,
        protected IncidentAlertService $alertService,
    ) {}

    /**
     * Create the SOS incident, or return the one this resident raised moments
     * ago so a panicking double-tap does not open two incidents.
     *
     * @param  array{latitude: float|string, longitude: float|string, accuracy?: float|string|null}  $location
     * @param  array{reason: SosReason, reason_other?: string|null, device_id?: string|null}  $details
     */
    public function trigger(User $user, array $location, array $details, string $channel): EmergencyRequest
    {
        return DB::transaction(function () use ($user, $location, $details, $channel) {
            $recent = $this->recentSos($user);

            if ($recent !== null) {
                return $recent;
            }

            $reason = $details['reason'];
            $reasonOther = $details['reason_other'] ?? null;
            $flags = $this->locationFlags($location);

            $request = EmergencyRequest::create([
                'resident_id' => $user->id,
                'description' => $this->describe($user, $reason, $reasonOther),
                'source' => $channel === 'sms' ? 'sms_fallback' : 'sos',
                'sos_channel' => $channel,
                'sos_reason' => $reason,
                'sos_reason_other' => $reasonOther,
                'device_id' => $details['device_id'] ?? null,
                'reporter_verification_status' => $user->verification_status,
                'category' => $reason->incidentCategory(),
                'urgency' => UrgencyLevel::Critical,
                'needs_review' => true,
                'review_reason' => $this->reviewReason($flags),
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'location_accuracy_meters' => $location['accuracy'] ?? null,
                'location_flags' => $flags,
                'status' => RequestStatus::Submitted,
            ]);

            $request->statusLogs()->create([
                'status' => RequestStatus::Submitted->value,
                'note' => sprintf('SOS raised by resident via %s.', $channel),
                'changed_by' => $user->id,
            ]);

            $request->transitionTo(RequestStatus::NeedsReview, $request->review_reason, $user->id);

            $this->auditLogger->record(
                action: 'sos.triggered',
                subject: $request,
                after: [
                    'channel' => $channel,
                    'reason' => $reason->value,
                    'reason_other' => $reasonOther,
                    'category' => $request->category,
                    'latitude' => (string) $request->latitude,
                    'longitude' => (string) $request->longitude,
                    'accuracy_meters' => $request->location_accuracy_meters === null
                        ? null
                        : (string) $request->location_accuracy_meters,
                    'location_flags' => $flags,
                    'device_id' => $request->device_id,
                    'reporter_verification_status' => $user->verification_status?->value,
                    'reporter_false_alarm_count' => $user->falseAlarmCount(),
                ],
                description: sprintf(
                    'SOS raised by %s via %s: %s.',
                    $user->name,
                    $channel,
                    $request->sosReasonLabel(),
                ),
                actor: $user,
            );

            return $request;
        });
    }

    /**
     * An SOS the same resident raised inside the cooldown window, if any. An
     * SOS raised before a responder cleared the cooldown no longer counts.
     */
    public function recentSos(User $user): ?EmergencyRequest
    {
        $since = now()->subSeconds($this->cooldownSeconds());
        $clearedAt = $this->cooldownClearedAt($user);

        if ($clearedAt !== null && $clearedAt->greaterThan($since)) {
            $since = $clearedAt;
        }

        return EmergencyRequest::where('resident_id', $user->id)
            ->whereIn('source', ['sos', 'sms_fallback'])
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->first();
    }

    public function cooldownSeconds(): int
    {
        return (int) config('sagip.sos.cooldown_seconds', 120);
    }

    /**
     * Seconds until this resident may raise another SOS; 0 when they may now.
     */
    public function cooldownRemainingSeconds(User $user): int
    {
        $recent = $this->recentSos($user);

        if ($recent === null) {
            return 0;
        }

        $availableAt = $recent->created_at->copy()->addSeconds($this->cooldownSeconds());

        return max(1, (int) ceil(now()->diffInSeconds($availableAt, absolute: true)));
    }

    /**
     * Personnel override: during an active response a responder or official
     * may lift the resident's cooldown so a genuine follow-up SOS (the
     * situation escalated, a second casualty) is not refused. The marker
     * expires with the cooldown window it overrides.
     */
    public function clearCooldown(User $resident, EmergencyRequest $context, User $clearedBy): void
    {
        // One second past "now", so the incident that started the cooldown —
        // possibly created within this same second — is excluded from it.
        Cache::put(
            $this->cooldownCacheKey($resident),
            now()->addSecond()->getTimestamp(),
            now()->addSeconds($this->cooldownSeconds()),
        );

        $this->auditLogger->record(
            action: 'sos.cooldown_cleared',
            subject: $context,
            after: [
                'resident_id' => $resident->id,
                'cleared_by' => $clearedBy->id,
            ],
            description: sprintf(
                '%s cleared the SOS cooldown for %s during request #%d.',
                $clearedBy->name,
                $resident->name,
                $context->id,
            ),
            actor: $clearedBy,
        );
    }

    /**
     * Informational flags on the reported location. They mark the incident for
     * a closer look; they never stop it from being filed or dispatched.
     *
     * @param  array{latitude: float|string, longitude: float|string, accuracy?: float|string|null}  $location
     * @return list<string>
     */
    public function locationFlags(array $location): array
    {
        $flags = [];
        $accuracy = $location['accuracy'] ?? null;

        if ($accuracy === null) {
            $flags[] = EmergencyRequest::FLAG_ACCURACY_UNKNOWN;
        } elseif ((float) $accuracy > (int) config('sagip.sos.poor_accuracy_meters', 100)) {
            $flags[] = EmergencyRequest::FLAG_POOR_ACCURACY;
        }

        $distanceFromHall = Geo::distanceInMeters(
            (float) config('sagip.hall.latitude'),
            (float) config('sagip.hall.longitude'),
            (float) $location['latitude'],
            (float) $location['longitude'],
        );

        if ($distanceFromHall > (int) config('sagip.sos.bounds_radius_meters', 3000)) {
            $flags[] = EmergencyRequest::FLAG_OUTSIDE_BOUNDS;
        }

        return $flags;
    }

    protected function cooldownClearedAt(User $user): ?Carbon
    {
        $timestamp = Cache::get($this->cooldownCacheKey($user));

        // In the app timezone: `created_at` is compared as a local datetime string.
        return $timestamp === null ? null : Carbon::createFromTimestamp($timestamp, config('app.timezone'));
    }

    protected function cooldownCacheKey(User $user): string
    {
        return 'sagip:sos:cooldown-cleared:'.$user->id;
    }

    /**
     * Feature 2 (in-app) and Feature 8 (email): alert every official plus the
     * on-duty responders whose specialization covers the incident. The recipient
     * rule itself lives in IncidentAlertService so the SOS path and the ordinary
     * report path cannot drift apart.
     */
    public function notifyResponders(EmergencyRequest $request): int
    {
        return $this->alertService->alert($request, new SosTriggered($request));
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(EmergencyRequest $request): Collection
    {
        return $this->alertService->recipientsFor($request);
    }

    /**
     * Feature 2: the SMS fallback. Fired when the browser gave up waiting for
     * the online endpoint, so the barangay hotline is reached over the SMS
     * gateway with the same payload the online call would have carried.
     */
    public function sendSmsFallback(User $user, EmergencyRequest $request): OutboundSmsMessage
    {
        $hotline = (string) config('sagip.sos.hotline_number');

        $message = $this->smsDispatcher->dispatch(
            recipient: $hotline,
            body: $this->smsBody($user, $request),
            purpose: OutboundSmsMessage::PURPOSE_SOS,
            user: $user,
            emergencyRequest: $request,
        );

        $this->auditLogger->record(
            action: 'sos.sms_fallback',
            subject: $request,
            after: [
                'driver' => $message->driver,
                'recipient' => $message->recipient,
                'status' => $message->status,
            ],
            description: sprintf(
                'SOS SMS fallback for %s via %s (%s).',
                $user->name,
                $message->driver,
                $message->status,
            ),
            actor: $user,
        );

        return $message;
    }

    /**
     * The text of the fallback message, also used to pre-fill the device's own
     * messaging app when the phone has no data connection at all.
     */
    public function smsBody(User $user, EmergencyRequest $request): string
    {
        return sprintf(
            'SAGIP SOS: %s (%s) needs help: %s. Location %s,%s. Request #%d at %s.',
            $user->name,
            $user->phone_number ?? 'no number on file',
            $request->sosReasonLabel() ?? 'reason not given',
            $request->latitude,
            $request->longitude,
            $request->id,
            $request->created_at->format('Y-m-d H:i'),
        );
    }

    protected function describe(User $user, SosReason $reason, ?string $reasonOther): string
    {
        return sprintf(
            'SOS alert raised by %s from the emergency button. Reason: %s.',
            $user->name,
            $reason === SosReason::Other ? sprintf('Other — "%s"', $reasonOther) : $reason->label(),
        );
    }

    /**
     * @param  list<string>  $flags
     */
    protected function reviewReason(array $flags): string
    {
        $reason = 'SOS button — needs immediate human triage.';

        if (array_intersect($flags, [EmergencyRequest::FLAG_POOR_ACCURACY, EmergencyRequest::FLAG_ACCURACY_UNKNOWN]) !== []) {
            $reason .= ' GPS accuracy is poor; confirm the location by phone.';
        }

        if (in_array(EmergencyRequest::FLAG_OUTSIDE_BOUNDS, $flags, true)) {
            $reason .= ' Location is outside the barangay.';
        }

        return $reason;
    }
}
