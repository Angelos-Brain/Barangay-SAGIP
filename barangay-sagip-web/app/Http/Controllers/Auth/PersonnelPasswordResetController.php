<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordDiffersFromContactDetails;
use App\Rules\PhilippineMobileNumber;
use App\Services\AuditLogger;
use App\Services\PersonnelLoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Forgot password for response personnel: prove the registered mobile number
 * with the same SMS code step as First Login, then choose a new password.
 *
 * Unlike First Login, the number step never says whether a number is
 * registered — a recovery form is where people probe for accounts. Only a
 * responder who has already chosen a password is actually sent a code.
 */
class PersonnelPasswordResetController extends Controller
{
    protected const PHONE_KEY = 'personnel_reset.phone';

    protected const VERIFIED_KEY = 'personnel_reset.verified';

    public function __construct(
        protected PersonnelLoginCodeService $codes,
        protected AuditLogger $auditLogger,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.personnel-forgot-password', [
            'phone' => $request->session()->get(self::PHONE_KEY),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20', new PhilippineMobileNumber],
        ]);

        $phone = PhilippineMobileNumber::normalize($validated['phone_number']);
        $request->session()->put(self::PHONE_KEY, $phone);
        $request->session()->forget(self::VERIFIED_KEY);

        $this->sendIfAllowed($phone);

        return redirect()->route('personnel.password.verify')
            ->with('status', 'If this number belongs to a registered responder, a reset code has been sent to it.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if ($phone === null) {
            return redirect()->route('personnel.password.request');
        }

        $user = $this->resettableUser($phone);

        return view('auth.personnel-verify', [
            'maskedPhone' => PersonnelOnboardingController::mask($phone),
            'resendIn' => $user === null ? 0 : $this->codes->resendAvailableIn($user),
            'step' => null,
            'checkRoute' => 'personnel.password.check',
            'resendRoute' => 'personnel.password.resend',
            'backRoute' => 'personnel.password.request',
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if ($phone === null) {
            return redirect()->route('personnel.password.request');
        }

        $this->sendIfAllowed($phone);

        return back()->with('status', sprintf(
            'If this number belongs to a registered responder, a new code has been sent. Codes can be requested once every %d seconds.',
            (int) config('sagip.personnel_login.resend_seconds', 60),
        ));
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if ($phone === null) {
            return redirect()->route('personnel.password.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:'.PersonnelLoginCodeService::CODE_LENGTH],
        ]);

        $user = $this->resettableUser($phone);

        if ($user === null || ! $this->codes->verify($user, $validated['code'], PersonnelLoginCodeService::PURPOSE_PASSWORD_RESET)) {
            throw ValidationException::withMessages([
                'code' => 'That code is incorrect or has expired. Check the SMS or request a new code.',
            ]);
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
                ->withErrors(['phone_number' => 'Your verification expired. Request a new code.']);
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
        $request->session()->forget([self::PHONE_KEY, self::VERIFIED_KEY]);

        // A successful reset lifts any sign-in lockout on this device.
        foreach ([$user->email, $user->phone_number] as $identifier) {
            if ($identifier !== null) {
                RateLimiter::clear(PersonnelLoginController::lockoutKey($identifier, (string) $request->ip()));
            }
        }

        $this->auditLogger->record(
            action: 'auth.password_reset',
            subject: $user,
            description: sprintf('%s reset their password after SMS verification.', $user->name),
            actor: $user,
        );

        return redirect()->route('personnel.login')
            ->with('status', 'Your password has been changed. Sign in with your new password.');
    }

    protected function sendIfAllowed(string $phone): void
    {
        $user = $this->resettableUser($phone);

        if ($user !== null && $this->codes->resendAvailableIn($user) === 0) {
            $this->codes->send($user, PersonnelLoginCodeService::PURPOSE_PASSWORD_RESET);
        }
    }

    /**
     * Only a responder who already chose a password has one to reset; anyone
     * earlier in onboarding belongs in First Login.
     */
    protected function resettableUser(string $phone): ?User
    {
        $user = $this->codes->findPersonnel($phone);

        return $user?->hasChosenPassword() ? $user : null;
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
