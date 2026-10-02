<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\GmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Matched on the inbox, so john.doe@gmail.com signs in to johndoe@gmail.com.
        $attempt = [
            'email_canonical' => GmailAddress::canonical($credentials['email']),
            'password' => $credentials['password'],
        ];

        if (! Auth::attempt($attempt, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // This is the resident-facing login. Staff accounts use the separate,
        // unlisted /admin/login instead — keeps the two experiences cleanly
        // apart rather than branching one shared login page by role.
        if (! Auth::user()->isResident()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This login is for residents. Barangay staff should use the staff login.',
            ]);
        }

        // An unverified resident is never signed in; they verify by email first.
        if (! Auth::user()->hasVerifiedEmail()) {
            $user = Auth::user();
            Auth::logout();

            return EmailVerificationController::redirectToNotice(
                $request,
                $user,
                'Check your Gmail to verify your account before signing in.',
            );
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
