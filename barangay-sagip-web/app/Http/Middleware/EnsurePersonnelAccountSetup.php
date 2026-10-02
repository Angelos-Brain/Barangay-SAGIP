<?php

namespace App\Http\Middleware;

use App\Enums\PersonnelAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a responder on the First Login screens until the account is active
 * (email verified by link, password chosen). Appended to the web group, so it
 * covers every page; the setup steps, the emailed links, and logout stay
 * reachable.
 */
class EnsurePersonnelAccountSetup
{
    /** @var list<string> */
    protected const ALWAYS_ALLOWED_ROUTES = ['verification.*', 'logout', 'admin.logout'];

    /** @var list<string> */
    protected const SETUP_ROUTES = ['account.setup', 'account.setup.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->needsAccountSetup() || $request->routeIs(...self::ALWAYS_ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Finish setting up your account first.');
        }

        // Signed in without ever verifying the email: start First Login over.
        if ($user->personnelAccountStatus() === PersonnelAccountStatus::Unclaimed) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('personnel.setup');
        }

        if ($request->routeIs(...self::SETUP_ROUTES)) {
            return $next($request);
        }

        return redirect()->route($user->nextAccountSetupRoute());
    }
}
