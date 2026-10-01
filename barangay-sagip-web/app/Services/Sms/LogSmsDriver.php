<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Feature 2: the default SMS driver for local development and tests. The
 * message is written to the application log instead of being sent, so the
 * fallback path is fully exercisable without a live gateway or an API key.
 */
class LogSmsDriver implements SmsSender
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $recipient, string $body): array
    {
        Log::channel(config('logging.default'))->warning('[SAGIP SMS] would send', [
            'recipient' => $recipient,
            'body' => $body,
        ]);

        return ['sent' => true, 'reference' => null, 'error' => null];
    }
}
