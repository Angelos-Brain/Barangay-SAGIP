<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff-only login, kept intentionally separate from the resident-facing
 * Auth\AuthenticatedSessionController and reachable only via the unlisted
 * /admin/login URL — it is never linked from any resident-facing page.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Responders sign in at /personnel/login, which enforces their lockout
        // and First Login status; this form gives them the generic failure.
        if (Auth::user()->isPersonnel()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()->isResident()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This login is for barangay staff only.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Responders sign in by mobile number, so send them back to that page.
        $wasPersonnel = Auth::user()?->isPersonnel() ?? false;

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasPersonnel ? 'personnel.login' : 'admin.login');
    }
}
