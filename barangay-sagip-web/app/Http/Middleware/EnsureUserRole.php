<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered in bootstrap/app.php as the `role` alias.
 *
 * Usage on routes:
 *   Route::middleware('role:official')->group(...)
 *   Route::middleware('role:admin,official')->group(...)
 *
 * Besides exact role values, two group tokens are accepted so that adding the
 * specialized responder roles in Feature 3 did not require rewriting every
 * route:
 *
 *   personnel  — any field-responder role (tanod, medical, fire_disaster,
 *                weather, peace_order, general_assistant, personnel)
 *   staff      — any non-resident role
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $this->authorizes($user->role, $roles)) {
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function authorizes(?UserRole $role, array $allowed): bool
    {
        if ($role === null) {
            return false;
        }

        foreach ($allowed as $token) {
            $matches = match ($token) {
                'personnel' => $role->isOperational(),
                'staff' => $role->isStaff(),
                default => $role->value === $token,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
