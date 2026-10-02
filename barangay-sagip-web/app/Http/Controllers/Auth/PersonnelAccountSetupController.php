<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\PasswordDiffersFromContactDetails;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * First Login, steps 3–4, once the responder has opened their emailed
 * verification link and is signed in: choose a password, which activates the
 * account, and land on "Account ready". EnsurePersonnelAccountSetup keeps
 * them on these screens until the password is chosen.
 */
class PersonnelAccountSetupController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

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
            description: sprintf('%s chose their account password during First Login; account is now %s.', $user->name, $user->personnelAccountStatus()->label()),
            actor: $user,
        );

        return redirect()->route('account.setup.ready');
    }

    public function ready(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->needsAccountSetup()) {
            return redirect()->route($user->nextAccountSetupRoute());
        }

        return view('auth.personnel-setup-ready', ['user' => $user, 'signedIn' => true]);
    }
}
