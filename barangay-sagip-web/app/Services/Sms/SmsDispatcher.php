<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Models\EmergencyRequest;
use App\Models\OutboundSmsMessage;
use App\Models\User;

/**
 * Feature 2: SOS SMS fallback.
 *
 * Records the message, hands it to the configured driver, then records the
 * outcome. Persisting first means an attempted fallback is auditable even if
 * the gateway call dies mid-flight.
 *
 * `$recordedBody` replaces the body in the stored record (and therefore the
 * audit trail) when the real text is a secret, such as a login code.
 */
class SmsDispatcher
{
    public function __construct(protected SmsSender $sender) {}

    public function dispatch(
        string $recipient,
        string $body,
        string $purpose = OutboundSmsMessage::PURPOSE_SOS,
        ?User $user = null,
        ?EmergencyRequest $emergencyRequest = null,
        ?string $recordedBody = null,
    ): OutboundSmsMessage {
        $message = OutboundSmsMessage::create([
            'user_id' => $user?->getKey(),
            'emergency_request_id' => $emergencyRequest?->getKey(),
            'purpose' => $purpose,
            'driver' => $this->sender->name(),
            'recipient' => $recipient,
            'body' => $recordedBody ?? $body,
            'status' => OutboundSmsMessage::STATUS_PENDING,
        ]);

        $result = $this->sender->send($recipient, $body);

        $message->update([
            'status' => $result['sent'] ? OutboundSmsMessage::STATUS_SENT : OutboundSmsMessage::STATUS_FAILED,
            'failure_reason' => $result['error'],
            'provider_reference' => $result['reference'],
            'sent_at' => $result['sent'] ? now() : null,
        ]);

        return $message;
    }
}
