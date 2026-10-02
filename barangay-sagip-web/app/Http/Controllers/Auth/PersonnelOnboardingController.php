<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PersonnelAccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\GmailAddress;
use App\Services\EmailLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * First Login, step 1: a responder an official added asks for their
 * verification link by entering the email the official registered.
 *
 * The link is also emailed when the official adds them; this page sends a
 * fresh one. Only emails on the personnel roster can start; anything else is
 * told to contact the barangay admin and nothing is sent. Opening the link
 * signs the responder in to choose a password (EmailVerificationController).
 */
class PersonnelOnboardingController extends Controller
{
    public function __construct(protected EmailLinkService $links) {}

    public function create(): View
    {
        return view('auth.personnel-setup-phone');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email'],
        ]);

        $user = self::findPersonnelByEmail($validated['email']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'This email is not registered as barangay personnel. Contact your barangay admin.',
            ]);
        }

        // A responder who verified but left before choosing a password gets a
        // new link too: opening it signs them back in to the password step.
        if ($user->personnelAccountStatus() === PersonnelAccountStatus::Active) {
            throw ValidationException::withMessages([
                'email' => 'This account is already set up. Sign in with your email and password.',
            ]);
        }

        if ($this->links->resendAvailableIn($user) === 0 && ! $this->links->send($user)) {
            throw ValidationException::withMessages([
                'email' => 'We could not send the email right now. Please try again in a moment.',
            ]);
        }

        return EmailVerificationController::redirectToNotice($request, $user, 'We emailed you a verification link.');
    }

    /**
     * The field-responder account registered to this email (matched on the
     * inbox). Officials, admins, and residents cannot use personnel sign-in,
     * and neither can an account whose responder record an official removed.
     */
    public static function findPersonnelByEmail(string $email): ?User
    {
        return User::query()
            ->where('email_canonical', GmailAddress::canonical($email))
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::operationalRoles()))
            ->whereHas('responsePersonnel')
            ->first();
    }
}
