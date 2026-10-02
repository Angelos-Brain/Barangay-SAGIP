<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use App\Notifications\ResetPersonnelPassword;
use App\Notifications\VerifyResidentEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single-use emailed links: email verification for residents and personnel,
 * and password reset for personnel.
 *
 * The token is random and only its SHA-256 hash is kept, in the cache,
 * together with the address it was sent to and its expiry. Sending a new link
 * replaces the old one, opening a link consumes it, and a link stops working
 * if the account's email changes afterwards.
 */
class EmailLinkService
{
    public const PURPOSE_VERIFY = 'verify';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Seconds before another link may be sent to this user; 0 when one may.
     */
    public function resendAvailableIn(User $user, string $purpose = self::PURPOSE_VERIFY): int
    {
        $pending = Cache::get($this->cacheKey($user, $purpose));

        if ($pending === null) {
            return 0;
        }

        return max(0, $pending['resend_at'] - now()->getTimestamp());
    }

    /**
     * Issue a fresh link and email it. Returns false when the mail could not
     * be handed to the mail service, so the caller can say so; the user may
     * then retry straight away.
     */
    public function send(User $user, string $purpose = self::PURPOSE_VERIFY): bool
    {
        $token = Str::random(64);
        $ttlHours = self::ttlHours();
        $key = $this->cacheKey($user, $purpose);

        Cache::put($key, [
            'hash' => hash('sha256', $token),
            'email' => $user->email,
            'expires_at' => now()->addHours($ttlHours)->getTimestamp(),
            'resend_at' => now()->addSeconds(self::resendSeconds())->getTimestamp(),
        ], now()->addHours($ttlHours));

        try {
            $user->notify($this->notification($user, $purpose, $token, $ttlHours));
        } catch (Throwable $exception) {
            Cache::forget($key);
            Log::error('Email link could not be sent.', ['user_id' => $user->id, 'purpose' => $purpose, 'error' => $exception->getMessage()]);

            return false;
        }

        $this->auditLogger->record(
            action: $purpose === self::PURPOSE_PASSWORD_RESET ? 'auth.password_reset_link_sent' : 'auth.email_verification_sent',
            subject: $user,
            after: ['email' => $user->email],
            description: sprintf('%s link sent to %s.', $purpose === self::PURPOSE_PASSWORD_RESET ? 'Password reset' : 'Email verification', $user->email),
            actor: $user,
        );

        return true;
    }

    /**
     * Consume a link. Returns false for a wrong, used, expired, or out-of-date
     * token. A verification link also marks the email verified.
     */
    public function consume(User $user, string $token, string $purpose = self::PURPOSE_VERIFY): bool
    {
        $key = $this->cacheKey($user, $purpose);
        $pending = Cache::get($key);

        if ($pending === null
            || $pending['expires_at'] < now()->getTimestamp()
            || $pending['email'] !== $user->email
            || ! hash_equals($pending['hash'], hash('sha256', $token))) {
            return false;
        }

        Cache::forget($key);

        if ($purpose === self::PURPOSE_VERIFY) {
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->auditLogger->record(
                action: 'auth.email_verified',
                subject: $user,
                after: ['email' => $user->email],
                description: sprintf('%s verified their email address.', $user->name),
                actor: $user,
            );
        }

        return true;
    }

    public static function ttlHours(): int
    {
        return (int) config('sagip.email_links.link_ttl_hours', 24);
    }

    public static function resendSeconds(): int
    {
        return (int) config('sagip.email_links.resend_seconds', 60);
    }

    protected function notification(User $user, string $purpose, string $token, int $ttlHours): object
    {
        if ($purpose === self::PURPOSE_PASSWORD_RESET) {
            return new ResetPersonnelPassword(
                route('personnel.password.link', ['user' => $user->id, 'token' => $token]),
                $ttlHours,
            );
        }

        $url = route('verification.verify', ['user' => $user->id, 'token' => $token]);

        return $user->isPersonnel()
            ? new ConfirmPersonnelEmail($url, $ttlHours)
            : new VerifyResidentEmail($url, $ttlHours);
    }

    protected function cacheKey(User $user, string $purpose): string
    {
        return sprintf('sagip:email-link:%s:%d', $purpose, $user->id);
    }
}
