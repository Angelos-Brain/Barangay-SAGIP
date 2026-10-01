<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PersonnelAccountStatus;
use App\Http\Controllers\Controller;
use App\Rules\PhilippineMobileNumber;
use App\Services\PersonnelLoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * First Login, steps 1–2: a responder an official added claims their account
 * by entering the registered mobile number and the code texted to it.
 *
 * Only numbers on the personnel roster can start; anything else is told to
 * contact the barangay admin and no SMS is sent. Once the code checks out the
 * responder is signed in, and EnsurePersonnelAccountSetup keeps them on the
 * remaining password and email steps until the account is active.
 */
class PersonnelOnboardingController extends Controller
{
    public const SESSION_KEY = 'personnel_setup.phone';

    public function __construct(protected PersonnelLoginCodeService $codes) {}

    public function create(Request $request): View
    {
        return view('auth.personnel-setup-phone', [
            'phone' => $request->session()->get(self::SESSION_KEY),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20', new PhilippineMobileNumber],
        ]);

        $phone = PhilippineMobileNumber::normalize($validated['phone_number']);
        $user = $this->codes->findPersonnel($phone);

        if ($user === null) {
            throw ValidationException::withMessages([
                'phone_number' => 'This number is not registered as barangay personnel. Contact your barangay admin.',
            ]);
        }

        if ($user->personnelAccountStatus() === PersonnelAccountStatus::Active) {
            throw ValidationException::withMessages([
                'phone_number' => 'This account is already set up. Sign in with your email or mobile number and password.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $phone);

        if ($this->codes->resendAvailableIn($user) === 0 && $this->codes->send($user) === null) {
            throw ValidationException::withMessages([
                'phone_number' => 'We could not send a text message right now. Contact your barangay admin.',
            ]);
        }

        return redirect()->route('personnel.setup.verify')
            ->with('status', 'We texted a verification code to your mobile number.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);
        $user = $phone === null ? null : $this->codes->findPersonnel($phone);

        if ($user === null) {
            return redirect()->route('personnel.setup');
        }

        return view('auth.personnel-verify', [
            'maskedPhone' => self::mask($phone),
            'resendIn' => $this->codes->resendAvailableIn($user),
            'step' => 2,
            'checkRoute' => 'personnel.setup.check',
            'resendRoute' => 'personnel.setup.resend',
            'backRoute' => 'personnel.setup',
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);
        $user = $phone === null ? null : $this->codes->findPersonnel($phone);

        if ($user === null) {
            return redirect()->route('personnel.setup');
        }

        $wait = $this->codes->resendAvailableIn($user);

        if ($wait > 0) {
            return back()->withErrors(['code' => sprintf('You can request a new code in %d seconds.', $wait)]);
        }

        if ($this->codes->send($user) === null) {
            return back()->withErrors(['code' => 'We could not send a text message right now. Contact your barangay admin.']);
        }

        return back()->with('status', 'A new code has been sent.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);
        $user = $phone === null ? null : $this->codes->findPersonnel($phone);

        if ($user === null) {
            return redirect()->route('personnel.setup');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:'.PersonnelLoginCodeService::CODE_LENGTH],
        ]);

        if (! $this->codes->verify($user, $validated['code'], PersonnelLoginCodeService::PURPOSE_SETUP)) {
            throw ValidationException::withMessages([
                'code' => 'That code is incorrect or has expired. Check the SMS or request a new code.',
            ]);
        }

        if ($user->phone_verified_at === null) {
            $user->update(['phone_verified_at' => now()]);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->nextAccountSetupRoute());
    }

    public static function mask(string $phone): string
    {
        return substr($phone, 0, 4).str_repeat('•', strlen($phone) - 7).substr($phone, -3);
    }
}
