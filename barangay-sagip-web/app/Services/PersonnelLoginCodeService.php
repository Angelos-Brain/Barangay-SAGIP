<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Models\OutboundSmsMessage;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * One-time SMS codes for response personnel.
 *
 * A responder proves they hold the mobile number an official registered with
 * a six-digit code texted to it — once during First Login, and again whenever
 * they reset a forgotten password. Only a hash of the code is kept (in the
 * cache, never in the SMS record or audit trail); it expires, it is consumed
 * on first use, and it is voided after too many wrong guesses.
 */
class PersonnelLoginCodeService
{
    public const CODE_LENGTH = 6;

    public const PURPOSE_SETUP = 'setup';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public function __construct(
        protected SmsDispatcher $smsDispatcher,
        protected SmsSender $smsSender,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * The field-responder account registered to this number, if exactly one
     * exists. Officials, admins, and residents cannot use this login, and
     * neither can an account whose responder record an official removed.
     */
    public function findPersonnel(string $mobileNumber): ?User
    {
        $normalized = PhilippineMobileNumber::normalize($mobileNumber);

        if ($normalized === null) {
            return null;
        }

        $matches = User::query()
            ->where('phone_number', $normalized)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::operationalRoles()))
            ->whereHas('responsePersonnel')
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Seconds before another code may be sent to this user; 0 when one may.
     */
    public function resendAvailableIn(User $user): int
    {
        $pending = Cache::get($this->cacheKey($user));

        if ($pending === null) {
            return 0;
        }

        return max(0, $pending['resend_at'] - now()->getTimestamp());
    }

    /**
     * Generate a fresh code, replacing any earlier one, and text it. Returns
     * null when no real SMS gateway is available to carry it.
     */
    public function send(User $user, string $purpose = self::PURPOSE_SETUP): ?OutboundSmsMessage
    {
        if (! $this->canDeliverCodes()) {
            $this->auditLogger->record(
                action: 'auth.login_code_undeliverable',
                subject: $user,
                description: 'Verification code not sent: no SMS gateway is configured for production.',
                actor: $user,
            );

            return null;
        }

        $code = str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $ttlMinutes = (int) config('sagip.personnel_login.code_ttl_minutes', 5);

        Cache::put($this->cacheKey($user), [
            'hash' => Hash::make($code),
            'purpose' => $purpose,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttlMinutes)->getTimestamp(),
            'resend_at' => now()->addSeconds((int) config('sagip.personnel_login.resend_seconds', 60))->getTimestamp(),
        ], now()->addMinutes($ttlMinutes));

        $message = $this->smsDispatcher->dispatch(
            recipient: $user->phone_number,
            body: sprintf(
                'Your Barangay SAGIP %s code is %s. It expires in %d minutes. Do not share this code with anyone.',
                $purpose === self::PURPOSE_PASSWORD_RESET ? 'password reset' : 'verification',
                $code,
                $ttlMinutes,
            ),
            purpose: OutboundSmsMessage::PURPOSE_LOGIN_CODE,
            user: $user,
            recordedBody: 'Barangay SAGIP verification code [redacted].',
        );

        $this->auditLogger->record(
            action: 'auth.login_code_sent',
            subject: $user,
            after: ['purpose' => $purpose, 'sms_status' => $message->status],
            description: sprintf('Verification code requested for %s by SMS (%s).', $user->name, $message->status),
            actor: $user,
        );

        return $message;
    }

    /**
     * Check a submitted code. A correct code for the same purpose is consumed;
     * a wrong one counts toward the attempt limit, after which the code is
     * voided and a new one must be requested.
     */
    public function verify(User $user, string $code, string $purpose = self::PURPOSE_SETUP): bool
    {
        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        if ($pending === null || $pending['expires_at'] < now()->getTimestamp() || ($pending['purpose'] ?? null) !== $purpose) {
            Cache::forget($key);
            $this->recordFailure($user, 'no valid code pending');

            return false;
        }

        if (Hash::check($code, $pending['hash'])) {
            Cache::forget($key);

            $this->auditLogger->record(
                action: 'auth.login_code_verified',
                subject: $user,
                after: ['purpose' => $purpose],
                description: sprintf('Verification code accepted for %s.', $user->name),
                actor: $user,
            );

            return true;
        }

        $pending['attempts']++;
        $this->recordFailure($user, sprintf('wrong code, attempt %d', $pending['attempts']));

        if ($pending['attempts'] >= (int) config('sagip.personnel_login.max_attempts', 5)) {
            Cache::forget($key);

            $this->auditLogger->record(
                action: 'auth.login_code_locked',
                subject: $user,
                description: sprintf('Verification code for %s voided after too many wrong attempts.', $user->name),
                actor: $user,
            );
        } else {
            Cache::put($key, $pending, now()->setTimestamp($pending['expires_at']));
        }

        return false;
    }

    /**
     * The `log` driver only writes the code to the server log. That is the
     * development mock; in production it would silently leak codes into the
     * log instead of reaching the responder, so it is refused.
     */
    public function canDeliverCodes(): bool
    {
        return ! ($this->smsSender->name() === 'log' && app()->isProduction());
    }

    protected function recordFailure(User $user, string $reason): void
    {
        $this->auditLogger->record(
            action: 'auth.login_code_failed',
            subject: $user,
            description: sprintf('Verification code rejected for %s (%s).', $user->name, $reason),
            actor: $user,
        );
    }

    protected function cacheKey(User $user): string
    {
        return 'sagip:personnel-login-code:'.$user->id;
    }
}
