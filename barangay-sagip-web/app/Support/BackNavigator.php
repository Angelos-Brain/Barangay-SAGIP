<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Feature 9: universal back navigation.
 *
 * Resolves where "back" goes for the current user. Two rules, in order:
 *
 *   1. The page they actually came from, provided it belongs to this
 *      application and is not the page they are already on.
 *   2. Otherwise the user's own home screen, which differs by role — a resident
 *      lands on the report form, staff land on the dashboard, and an account
 *      still awaiting verification lands on its holding page.
 *
 * Resolving this server-side rather than calling history.back() means the target
 * is always a route the user may actually open: a resident who was 403'd off a
 * staff page, or who followed a link from outside the app, still gets a sane
 * destination instead of bouncing out of the application.
 */
final class BackNavigator
{
    /**
     * Paths that must never be a back target — going "back" to them either
     * signs the user out or drops them on a page they just left deliberately.
     */
    private const EXCLUDED_PATH_PREFIXES = [
        'login',
        'register',
        'logout',
        'admin/login',
        'admin/logout',
        'personnel/login',
        'sos',
    ];

    public function homeUrlFor(?User $user): string
    {
        if ($user === null) {
            return route('login');
        }

        if ($user->isResident()) {
            return $user->isVerified()
                ? route('requests.create')
                : route('account.verification.pending');
        }

        return route('dashboard');
    }

    /**
     * Whether the back control should render at all. It is hidden on the user's
     * own home screen, where there is nothing above to go back to.
     */
    public function shouldShow(?User $user, string $currentUrl): bool
    {
        if ($user === null) {
            return false;
        }

        return ! $this->isSameUrl($currentUrl, $this->homeUrlFor($user));
    }

    public function resolve(?User $user, string $currentUrl, ?string $previousUrl): string
    {
        $home = $this->homeUrlFor($user);

        if ($previousUrl === null || ! $this->isUsablePrevious($currentUrl, $previousUrl)) {
            return $home;
        }

        return $previousUrl;
    }

    /**
     * A short label for the destination, so the control says where it goes
     * instead of just "Back".
     */
    public function labelFor(?User $user, string $backUrl): string
    {
        return $this->isSameUrl($backUrl, $this->homeUrlFor($user))
            ? ($user?->isResident() ? 'Back to reporting' : 'Back to dashboard')
            : 'Back';
    }

    private function isUsablePrevious(string $currentUrl, string $previousUrl): bool
    {
        if (! $this->belongsToApplication($previousUrl)) {
            return false;
        }

        if ($this->isSameUrl($currentUrl, $previousUrl)) {
            return false;
        }

        return ! $this->isExcluded($previousUrl);
    }

    private function belongsToApplication(string $url): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        // A relative URL is by definition ours.
        if ($host === null) {
            return true;
        }

        return $appHost !== null && strcasecmp($host, $appHost) === 0;
    }

    private function isExcluded(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (self::EXCLUDED_PATH_PREFIXES as $excluded) {
            if ($path === $excluded || Str::startsWith($path, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    private function isSameUrl(string $first, string $second): bool
    {
        return $this->normalize($first) === $this->normalize($second);
    }

    /**
     * Compare by path and query only, so http/https and a trailing slash do not
     * make the same page look like two different ones.
     */
    private function normalize(string $url): string
    {
        $parts = parse_url($url);
        $path = trim($parts['path'] ?? '', '/');
        $query = $parts['query'] ?? '';

        return $query === '' ? $path : $path.'?'.$query;
    }
}
