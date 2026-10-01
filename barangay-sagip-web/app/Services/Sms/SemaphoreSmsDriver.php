<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Feature 2: live SMS delivery through Semaphore.co, the gateway most Philippine
 * LGUs use. Driven entirely by Laravel's HTTP client, so no extra Composer
 * dependency is required.
 */
class SemaphoreSmsDriver implements SmsSender
{
    public function name(): string
    {
        return 'semaphore';
    }

    public function send(string $recipient, string $body): array
    {
        $apiKey = config('sagip.sms.semaphore.api_key');

        if (blank($apiKey)) {
            return [
                'sent' => false,
                'reference' => null,
                'error' => 'SAGIP_SEMAPHORE_API_KEY is not configured.',
            ];
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('sagip.sms.semaphore.timeout', 10))
                ->post((string) config('sagip.sms.semaphore.endpoint'), array_filter([
                    'apikey' => $apiKey,
                    'number' => $recipient,
                    'message' => $body,
                    'sendername' => config('sagip.sms.semaphore.sender_name'),
                ]));

            if ($response->failed()) {
                return [
                    'sent' => false,
                    'reference' => null,
                    'error' => 'Gateway returned HTTP '.$response->status(),
                ];
            }

            $payload = $response->json();
            $reference = is_array($payload) ? ($payload[0]['message_id'] ?? null) : null;

            return [
                'sent' => true,
                'reference' => $reference === null ? null : (string) $reference,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Semaphore SMS send failed: '.$e->getMessage());

            return ['sent' => false, 'reference' => null, 'error' => $e->getMessage()];
        }
    }
}
