<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\GmailAddress;
use App\Services\AuditLogger;
use App\Services\EmailLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Subsequent logins for response personnel: the account email plus the
 * password chosen in First Login. Kept apart from the resident login and the
 * staff login.
 *
 * Every failure gets the same message, so the form never reveals whether an
 * email exists. Five failures from one device lock that email out for a
 * while. An account whose email is not yet verified is not signed in; it is
 * sent to "Check your Gmail" for a new link.
 */
class PersonnelLoginController extends Controller
{
    public const FAILED_MESSAGE = 'Incorrect details. Check your email and password.';

    public function __construct(
        protected EmailLinkService $links,
        protected AuditLogger $auditLogger,
    ) {}

    public function create(): View
    {
        return view('auth.personnel-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Enter your email.',
        ]);

        $lockKey = self::lockoutKey($validated['email'], (string) $request->ip());

        if (RateLimiter::tooManyAttempts($lockKey, $this->maxAttempts())) {
            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Too many failed attempts. Try again in %d minutes, or reset your password.',
                    (int) ceil(RateLimiter::availableIn($lockKey) / 60),
                ),
            ]);
        }

        $user = PersonnelOnboardingController::findPersonnelByEmail($validated['email']);

        // Always run one hash check so an unknown email takes as long as a
        // wrong password.
        $passwordMatches = Hash::check($validated['password'], $user?->password ?? $this->dummyHash());

        if ($user === null || ! $passwordMatches || ! $user->hasChosenPassword()) {
            $this->recordFailure($user, $lockKey);

            throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
        }

        RateLimiter::clear($lockKey);

        if (! $user->hasVerifiedEmail()) {
            if ($this->links->resendAvailableIn($user) === 0) {
                $this->links->send($user);
            }

            return EmailVerificationController::redirectToNotice(
                $request,
                $user,
                'Check your Gmail to verify your account before signing in.',
            );
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $user->needsAccountSetup()
            ? redirect()->route($user->nextAccountSetupRoute())
                ->with('status', 'Finish setting up your account to continue.')
            : redirect()->intended(route('dashboard'));
    }

    protected function recordFailure(?User $user, string $lockKey): void
    {
        RateLimiter::hit($lockKey, (int) config('sagip.personnel_login.lockout_minutes', 15) * 60);

        $this->auditLogger->record(
            action: 'auth.personnel_login_failed',
            subject: $user,
            description: $user === null
                ? 'Failed personnel sign-in for an unrecognised email.'
                : sprintf('Failed personnel sign-in for %s.', $user->name),
            actor: $user,
        );

        if (RateLimiter::attempts($lockKey) === $this->maxAttempts()) {
            $this->auditLogger->record(
                action: 'auth.personnel_login_locked',
                subject: $user,
                description: sprintf(
                    'Personnel sign-in locked for %d minutes after %d failed attempts.',
                    (int) config('sagip.personnel_login.lockout_minutes', 15),
                    $this->maxAttempts(),
                ),
                actor: $user,
            );
        }
    }

    /**
     * Keyed on the inbox and the client IP, so an attacker guessing at one
     * responder's account cannot also lock that responder out of their own
     * phone mid-emergency.
     */
    public static function lockoutKey(string $email, string $ip): string
    {
        return 'personnel-login-lockout:'.sha1(GmailAddress::canonical($email).'|'.$ip);
    }

    protected function maxAttempts(): int
    {
        return (int) config('sagip.personnel_login.max_login_attempts', 5);
    }

    protected function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('sagip-personnel-login-timing-guard');
    }
}
