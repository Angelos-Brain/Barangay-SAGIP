<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature 1: Account Verification gate.
 *
 * Registered in bootstrap/app.php as the `verified.account` alias and applied
 * to the emergency features only — a pending resident may still finish their
 * profile and read notifications, which is exactly what they need to do while
 * waiting for an official to review them.
 */
class EnsureAccountVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You are not authorized to access this page.');
        }

        if ($user->isVerified()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, $this->message($user->isRejected()));
        }

        return redirect()
            ->route('account.verification.pending')
            ->with('status', $this->message($user->isRejected()));
    }

    protected function message(bool $rejected): string
    {
        return $rejected
            ? 'Your account was not approved, so emergency reporting is unavailable. Please visit the barangay hall.'
            : 'Your account is still awaiting verification by a barangay official. Emergency reporting unlocks once it is approved.';
    }
}
