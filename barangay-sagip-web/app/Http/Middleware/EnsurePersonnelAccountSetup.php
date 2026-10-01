<?php

namespace App\Http\Middleware;

use App\Enums\PersonnelAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a responder on the First Login screens until the account is active
 * (mobile verified, password chosen, email confirmed). Appended to the web
 * group, so it covers every page; the setup steps, the emailed confirmation
 * link, and logout stay reachable.
 */
class EnsurePersonnelAccountSetup
{
    /** @var list<string> */
    protected const ALLOWED_ROUTES = ['account.setup', 'account.setup.*', 'personnel.email.confirm', 'logout', 'admin.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->needsAccountSetup() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Finish setting up your account first.');
        }

        // Signed in without ever verifying the mobile number: start First Login over.
        if ($user->personnelAccountStatus() === PersonnelAccountStatus::Unclaimed) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('personnel.setup');
        }

        return redirect()->route($user->nextAccountSetupRoute());
    }
}
