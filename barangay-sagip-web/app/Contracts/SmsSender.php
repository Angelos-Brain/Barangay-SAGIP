<?php

namespace App\Contracts;

/**
 * Feature 2: SOS SMS fallback.
 *
 * One method, one job: hand a message to the gateway and say whether it left.
 * Implementations must not throw — a failed send is a returned result, because
 * the caller is already handling an emergency.
 */
interface SmsSender
{
    /**
     * The driver name recorded against each message, e.g. `log` or `semaphore`.
     */
    public function name(): string;

    /**
     * @return array{sent: bool, reference: ?string, error: ?string}
     */
    public function send(string $recipient, string $body): array;
}
