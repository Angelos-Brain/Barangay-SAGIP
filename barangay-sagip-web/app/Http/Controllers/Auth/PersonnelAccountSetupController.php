<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PersonnelAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\DeliverableEmail;
use App\Rules\PasswordDiffersFromContactDetails;
use App\Services\AuditLogger;
use App\Services\PersonnelEmailConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * First Login, steps 3–5, once the responder has verified their mobile number
 * and is signed in: choose a password, confirm an email address by link, and
 * land on "Account ready". EnsurePersonnelAccountSetup keeps them on these
 * screens until the email is confirmed and the account becomes active.
 */
class PersonnelAccountSetupController extends Controller
{
    public function __construct(
        protected PersonnelEmailConfirmationService $emailConfirmation,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Resume at whichever step is still due.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route($user->needsAccountSetup() ? $user->nextAccountSetupRoute() : 'dashboard');
    }

    public function editPassword(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsAccountSetup()) {
            return redirect()->route('dashboard');
        }

        return view('auth.personnel-account-setup', ['user' => $user]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsAccountSetup()) {
            return redirect()->route('dashboard');
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

        $user->update([
            'password' => $validated['password'],
            'account_setup_completed_at' => $user->account_setup_completed_at ?? now(),
        ]);

        $this->auditLogger->record(
            action: 'auth.password_set',
            subject: $user,
            description: sprintf('%s chose their account password during First Login.', $user->name),
            actor: $user,
        );

        return redirect()->route('account.setup.email');
    }

    public function editEmail(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsAccountSetup() || ! $user->hasChosenPassword()) {
            return redirect()->route($user->needsAccountSetup() ? $user->nextAccountSetupRoute() : 'account.setup.ready');
        }

        return view('auth.personnel-setup-email', [
            'user' => $user,
            'linkSent' => $this->emailConfirmation->resendAvailableIn($user) > 0 || $request->session()->has('email_link_sent'),
            'resendIn' => $this->emailConfirmation->resendAvailableIn($user),
        ]);
    }

    /**
     * Save the address (pre-filled from the admin record, correctable here)
     * and send the confirmation link to it.
     */
    public function sendEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsAccountSetup() || ! $user->hasChosenPassword()) {
            return redirect()->route('account.setup');
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                $this->emailFormatRule(),
                new DeliverableEmail,
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.email' => 'Enter a valid email address, for example juan.delacruz@gmail.com.',
        ]);

        $changed = $validated['email'] !== $user->email;

        if ($changed) {
            $user->forceFill(['email' => $validated['email'], 'email_verified_at' => null])->save();
        }

        $wait = $this->emailConfirmation->resendAvailableIn($user);

        if (! $changed && $wait > 0) {
            return back()->withErrors(['email' => sprintf('A link was just sent. You can request another in %d seconds.', $wait)]);
        }

        $this->emailConfirmation->send($user);

        return redirect()->route('account.setup.email')
            ->with('email_link_sent', true)
            ->with('status', sprintf('We sent a confirmation link to %s.', $user->email));
    }

    public function resendEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsAccountSetup() || ! $user->hasChosenPassword()) {
            return redirect()->route('account.setup');
        }

        $wait = $this->emailConfirmation->resendAvailableIn($user);

        if ($wait > 0) {
            return back()->withErrors(['email' => sprintf('You can request another link in %d seconds.', $wait)]);
        }

        $this->emailConfirmation->send($user);

        return back()->with('email_link_sent', true)
            ->with('status', sprintf('A new confirmation link was sent to %s.', $user->email));
    }

    public function ready(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->needsAccountSetup()) {
            return redirect()->route($user->nextAccountSetupRoute());
        }

        return view('auth.personnel-setup-ready', ['user' => $user, 'signedIn' => true]);
    }

    /**
     * The emailed link. It may be opened on another device, so it works
     * whether or not the responder is signed in there.
     */
    public function confirmEmail(Request $request, User $user, string $token): View|RedirectResponse
    {
        $alreadyActive = $user->isPersonnel() && $user->personnelAccountStatus() === PersonnelAccountStatus::Active;

        if (! $alreadyActive && ! ($user->isPersonnel() && $this->emailConfirmation->confirm($user, $token))) {
            return redirect()->route($request->user()?->is($user) ? 'account.setup.email' : 'personnel.login')
                ->withErrors(['email' => 'This confirmation link is invalid, already used, or expired. Sign in to request a new one.']);
        }

        if ($request->user()?->is($user)) {
            return redirect()->route('account.setup.ready');
        }

        return view('auth.personnel-setup-ready', ['user' => $user, 'signedIn' => false]);
    }

    /**
     * Feature 6: the same strict RFC and anti-spoofing check the resident
     * registration form applies.
     */
    protected function emailFormatRule(): Rules\Email
    {
        $rule = Rule::email()->strict()->preventSpoofing();

        if (config('sagip.registration.email.verify_mx')) {
            $rule->validateMxRecord();
        }

        return $rule;
    }
}
