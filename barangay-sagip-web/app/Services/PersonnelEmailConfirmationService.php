<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * First Login, step 4: confirm the responder's email with a single-use link.
 *
 * Only a SHA-256 hash of the token is kept, in the cache, together with the
 * address it was sent to — so a link stops working if the email is changed
 * afterwards. Sending a new link replaces the old one.
 */
class PersonnelEmailConfirmationService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Seconds before another link may be sent to this user; 0 when one may.
     */
    public function resendAvailableIn(User $user): int
    {
        $pending = Cache::get($this->cacheKey($user));

        if ($pending === null) {
            return 0;
        }

        return max(0, $pending['resend_at'] - now()->getTimestamp());
    }

    public function send(User $user): void
    {
        $token = Str::random(64);
        $ttlHours = (int) config('sagip.personnel_login.email_link_ttl_hours', 24);

        Cache::put($this->cacheKey($user), [
            'hash' => hash('sha256', $token),
            'email' => $user->email,
            'expires_at' => now()->addHours($ttlHours)->getTimestamp(),
            'resend_at' => now()->addSeconds((int) config('sagip.personnel_login.resend_seconds', 60))->getTimestamp(),
        ], now()->addHours($ttlHours));

        $user->notify(new ConfirmPersonnelEmail(
            route('personnel.email.confirm', ['user' => $user->id, 'token' => $token]),
            $ttlHours,
        ));

        $this->auditLogger->record(
            action: 'auth.email_confirmation_sent',
            subject: $user,
            after: ['email' => $user->email],
            description: sprintf('Email confirmation link sent to %s.', $user->email),
            actor: $user,
        );
    }

    /**
     * Consume the token and mark the email confirmed. Returns false for a
     * wrong, used, expired, or out-of-date link.
     */
    public function confirm(User $user, string $token): bool
    {
        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        if ($pending === null
            || $pending['expires_at'] < now()->getTimestamp()
            || $pending['email'] !== $user->email
            || ! hash_equals($pending['hash'], hash('sha256', $token))) {
            return false;
        }

        Cache::forget($key);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->auditLogger->record(
            action: 'auth.email_confirmed',
            subject: $user,
            after: ['email' => $user->email],
            description: sprintf('%s confirmed their email; account is now %s.', $user->name, $user->personnelAccountStatus()->label()),
            actor: $user,
        );

        return true;
    }

    protected function cacheKey(User $user): string
    {
        return 'sagip:personnel-email-confirmation:'.$user->id;
    }
}
