<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Email verification for residents and personnel.
 *
 * An unverified account is never signed in: registration, a sign-in attempt,
 * or personnel First Login park its id in the session and show the "Check
 * your Gmail" screen, which can resend the link once a cooldown passes.
 * Opening the link verifies the email. A resident then signs in; a responder
 * is signed in straight away to choose their password.
 */
class EmailVerificationController extends Controller
{
    public const SESSION_KEY = 'email_verification.user_id';

    public function __construct(protected EmailLinkService $links) {}

    /**
     * Park an unverified account and show the "Check your Gmail" screen.
     */
    public static function redirectToNotice(Request $request, User $user, string $status): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, $user->id);

        return redirect()->route('verification.notice')->with('status', $status);
    }

    public function notice(Request $request): View
    {
        $user = $this->pendingUser($request);

        return view('auth.verify-email', [
            'user' => $user,
            'resendIn' => $user === null ? 0 : $this->links->resendAvailableIn($user),
            'ttlHours' => EmailLinkService::ttlHours(),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('verification.notice');
        }

        if ($this->isDone($user)) {
            return $this->toSignIn($user, 'Your email is already verified. You can sign in.');
        }

        $wait = $this->links->resendAvailableIn($user);

        if ($wait > 0) {
            return back()->withErrors(['link' => sprintf('You can request another email in %d seconds.', $wait)]);
        }

        if (! $this->links->send($user)) {
            return back()->withErrors(['link' => 'We could not send the email right now. Please try again in a moment.']);
        }

        return back()->with('status', sprintf('A new verification link was sent to %s.', $user->email));
    }

    /**
     * The emailed link. It may be opened on another device, signed in or not.
     */
    public function verify(Request $request, User $user, string $token): RedirectResponse
    {
        if (! $this->links->consume($user, $token)) {
            if ($this->isDone($user)) {
                return $this->toSignIn($user, 'This link was already used and your email is verified. You can sign in.');
            }

            $request->session()->put(self::SESSION_KEY, $user->id);

            return redirect()->route('verification.notice')
                ->withErrors(['link' => 'This verification link has expired or was already used. Request a new one below.']);
        }

        $request->session()->forget(self::SESSION_KEY);

        if (! $user->isPersonnel()) {
            return $this->toSignIn($user, 'Your email is verified. You can now sign in.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $user->needsAccountSetup()
            ? redirect()->route($user->nextAccountSetupRoute())->with('status', 'Your email is verified. Choose your password to finish setting up.')
            : redirect()->route('dashboard');
    }

    protected function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id === null ? null : User::find($id);
    }

    /**
     * Verified with nothing left to do by link. A responder who verified but
     * never chose a password still needs a link to get back in to do so.
     */
    protected function isDone(User $user): bool
    {
        return $user->hasVerifiedEmail() && ! $user->needsAccountSetup();
    }

    protected function toSignIn(User $user, string $status): RedirectResponse
    {
        return redirect()->route($user->isPersonnel() ? 'personnel.login' : 'login')->with('status', $status);
    }
}
