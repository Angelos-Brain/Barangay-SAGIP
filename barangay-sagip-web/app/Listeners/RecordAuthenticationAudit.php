<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;

/**
 * Feature 3: Audit Log.
 *
 * Authentication is state-changing but touches no auditable model, so it is
 * recorded from the framework's own auth events. Listening to the events rather
 * than patching each controller means every login path — resident login, staff
 * login, remember-me — lands in the trail.
 *
 * The methods deliberately avoid the `handle*` prefix: Laravel's listener
 * auto-discovery would bind those on top of the explicit registrations in
 * AppServiceProvider and every event would be recorded twice.
 */
class RecordAuthenticationAudit
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function recordLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->auditLogger->record(
            action: 'auth.login',
            subject: $user,
            description: sprintf('Signed in via the %s guard.', $event->guard),
            actor: $user,
        );
    }

    public function recordLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->auditLogger->record(
            action: 'auth.logout',
            subject: $user,
            description: 'Signed out.',
            actor: $user,
        );
    }

    /**
     * A rejected sign-in has no actor, so the attempted email is recorded in
     * the description instead. Credentials themselves are never stored.
     */
    public function recordFailure(Failed $event): void
    {
        // Resident sign-in matches on the inbox, so it passes `email_canonical`.
        $email = $event->credentials['email'] ?? $event->credentials['email_canonical'] ?? null;

        $this->auditLogger->record(
            action: 'auth.failed',
            description: $email === null
                ? 'Failed sign-in attempt.'
                : sprintf('Failed sign-in attempt for %s.', $email),
        );
    }

    public function recordRegistration(Registered $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->auditLogger->record(
            action: 'auth.registered',
            subject: $user,
            description: 'New account registered.',
            actor: $user,
        );
    }
}
