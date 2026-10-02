<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordDiffersFromContactDetails;
use App\Services\AuditLogger;
use App\Services\EmailLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Forgot password for response personnel: an emailed single-use link (the
 * same token handling as email verification), then choose a new password.
 *
 * Unlike First Login, the email step never says whether an address is
 * registered — a recovery form is where people probe for accounts. Only an
 * active responder is actually sent a link.
 */
class PersonnelPasswordResetController extends Controller
{
    protected const VERIFIED_KEY = 'personnel_reset.verified';

    public function __construct(
        protected EmailLinkService $links,
        protected AuditLogger $auditLogger,
    ) {}

    public function create(): View
    {
        return view('auth.personnel-forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email'],
        ]);

        $request->session()->forget(self::VERIFIED_KEY);

        $user = $this->resettableUser($validated['email']);

        if ($user !== null && $this->links->resendAvailableIn($user, EmailLinkService::PURPOSE_PASSWORD_RESET) === 0) {
            $this->links->send($user, EmailLinkService::PURPOSE_PASSWORD_RESET);
        }

        return back()->withInput()->with('status', sprintf(
            'If this email belongs to a registered responder, we sent it a password reset link. Links can be requested once every %d seconds.',
            EmailLinkService::resendSeconds(),
        ));
    }

    /**
     * The emailed link. Opening it consumes the token and allows choosing a
     * new password on this device for the next 10 minutes.
     */
    public function openLink(Request $request, User $user, string $token): RedirectResponse
    {
        if (! $user->isPersonnel() || ! $this->links->consume($user, $token, EmailLinkService::PURPOSE_PASSWORD_RESET)) {
            return redirect()->route('personnel.password.request')
                ->withErrors(['email' => 'This reset link has expired or was already used. Enter your email to get a new one.']);
        }

        $request->session()->put(self::VERIFIED_KEY, [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ]);

        return redirect()->route('personnel.password.reset');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if ($this->verifiedUser($request) === null) {
            return redirect()->route('personnel.password.request');
        }

        return view('auth.personnel-reset-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->verifiedUser($request);

        if ($user === null) {
            return redirect()->route('personnel.password.request')
                ->withErrors(['email' => 'Your reset link expired. Request a new one.']);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::min(8),
                new PasswordDiffersFromContactDetails($user->phone_number, $user->email),
            ],
        ]);

        $user->update(['password' => $validated['password']]);
        $request->session()->forget(self::VERIFIED_KEY);

        // A successful reset lifts any sign-in lockout on this device.
        RateLimiter::clear(PersonnelLoginController::lockoutKey($user->email, (string) $request->ip()));

        $this->auditLogger->record(
            action: 'auth.password_reset',
            subject: $user,
            description: sprintf('%s reset their password using an emailed link.', $user->name),
            actor: $user,
        );

        return redirect()->route('personnel.login')
            ->with('status', 'Your password has been changed. Sign in with your new password.');
    }

    /**
     * Only an active responder has a password to reset; anyone earlier in
     * onboarding belongs in First Login.
     */
    protected function resettableUser(string $email): ?User
    {
        $user = PersonnelOnboardingController::findPersonnelByEmail($email);

        return $user !== null && ! $user->needsAccountSetup() ? $user : null;
    }

    protected function verifiedUser(Request $request): ?User
    {
        $verified = $request->session()->get(self::VERIFIED_KEY);

        if (! is_array($verified) || $verified['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return User::find($verified['user_id']);
    }
}
