<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Services\AuditLogger;
use App\Services\PersonnelLoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Subsequent logins for response personnel: one field that takes either the
 * account email or the registered mobile number, plus the password chosen in
 * First Login. Kept apart from the resident login and the staff login.
 *
 * Every failure gets the same message, so the form never reveals whether an
 * email or number exists. Five failures from one device lock that identifier
 * out for a while. An account whose setup is unfinished is signed in only to
 * resume First Login — EnsurePersonnelAccountSetup keeps it off everything else.
 */
class PersonnelLoginController extends Controller
{
    public const FAILED_MESSAGE = 'Incorrect details. Check your email or mobile number and password.';

    public function __construct(
        protected PersonnelLoginCodeService $codes,
        protected AuditLogger $auditLogger,
    ) {}

    public function create(): View
    {
        return view('auth.personnel-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Enter your email or mobile number.',
        ]);

        $lockKey = self::lockoutKey($validated['identifier'], (string) $request->ip());

        if (RateLimiter::tooManyAttempts($lockKey, $this->maxAttempts())) {
            throw ValidationException::withMessages([
                'identifier' => sprintf(
                    'Too many failed attempts. Try again in %d minutes, or reset your password.',
                    (int) ceil(RateLimiter::availableIn($lockKey) / 60),
                ),
            ]);
        }

        $user = $this->findByIdentifier($validated['identifier']);

        // Always run one hash check so an unknown identifier takes as long as a
        // wrong password.
        $passwordMatches = Hash::check($validated['password'], $user?->password ?? $this->dummyHash());

        if ($user === null || ! $passwordMatches || ! $user->hasChosenPassword()) {
            $this->recordFailure($user, $lockKey);

            throw ValidationException::withMessages(['identifier' => self::FAILED_MESSAGE]);
        }

        RateLimiter::clear($lockKey);

        Auth::login($user);
        $request->session()->regenerate();

        return $user->needsAccountSetup()
            ? redirect()->route($user->nextAccountSetupRoute())
                ->with('status', 'Finish setting up your account to continue.')
            : redirect()->intended(route('dashboard'));
    }

    /**
     * An address containing "@" is an email; anything else is read as a
     * Philippine mobile number.
     */
    protected function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            $user = User::where('email', strtolower($identifier))->first();

            return $user !== null && $user->isPersonnel() && $user->responsePersonnel()->exists() ? $user : null;
        }

        return PhilippineMobileNumber::normalize($identifier) === null
            ? null
            : $this->codes->findPersonnel($identifier);
    }

    protected function recordFailure(?User $user, string $lockKey): void
    {
        RateLimiter::hit($lockKey, (int) config('sagip.personnel_login.lockout_minutes', 15) * 60);

        $this->auditLogger->record(
            action: 'auth.personnel_login_failed',
            subject: $user,
            description: $user === null
                ? 'Failed personnel sign-in for an unrecognised email or mobile number.'
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
     * Keyed on the normalised identifier and the client IP, so an attacker
     * guessing at one responder's account cannot also lock that responder
     * out of their own phone mid-emergency.
     */
    public static function lockoutKey(string $identifier, string $ip): string
    {
        $identifier = trim($identifier);
        $normalized = str_contains($identifier, '@')
            ? strtolower($identifier)
            : (PhilippineMobileNumber::normalize($identifier) ?? strtolower($identifier));

        return 'personnel-login-lockout:'.sha1($normalized.'|'.$ip);
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
