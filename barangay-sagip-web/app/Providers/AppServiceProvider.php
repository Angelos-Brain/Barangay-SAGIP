<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Enums\Permission;
use App\Listeners\RecordAuthenticationAudit;
use App\Models\User;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SemaphoreSmsDriver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerSmsSender();
    }

    /**
     * Feature 2: resolve the SOS SMS fallback driver from config. `log` keeps
     * local development and the test suite free of a live gateway.
     */
    protected function registerSmsSender(): void
    {
        $this->app->singleton(SmsSender::class, function () {
            return match (config('sagip.sms.driver')) {
                'semaphore' => new SemaphoreSmsDriver,
                default => new LogSmsDriver,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
        $this->registerAuditListeners();

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Personnel sign-in and opening emailed links. Loose enough that the
        // controller's own 5-strike lockout is what people actually hit.
        RateLimiter::for('personnel-login', function (Request $request) {
            $identifier = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by('login:'.$identifier.'|'.$request->ip()),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        // Anything that emails a verification or reset link, per address and per IP.
        // The 60-second resend cooldown in EmailLinkService applies on top.
        RateLimiter::for('email-link', function (Request $request) {
            $target = strtolower(trim((string) ($request->input('email')
                ?? $request->session()->get('email_verification.user_id'))));

            return [
                Limit::perMinute(3)->by('link:'.$target.'|'.$request->ip()),
                Limit::perHour(10)->by('link-target:'.$target),
                Limit::perMinute(10)->by('link-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('emergency-request', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('sos', function (Request $request) {
            return Limit::perMinute(3)->by((string) $request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Feature 3: every Permission case becomes a Gate ability resolved from the
     * role → permissions map on UserRole, so authorization has one source of
     * truth for routes, controllers, and Blade views alike.
     */
    protected function registerPermissionGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user) => $user->hasPermission($permission),
            );
        }
    }

    /**
     * Feature 3: record authentication events in the audit trail.
     */
    protected function registerAuditListeners(): void
    {
        Event::listen(Login::class, [RecordAuthenticationAudit::class, 'recordLogin']);
        Event::listen(Logout::class, [RecordAuthenticationAudit::class, 'recordLogout']);
        Event::listen(Failed::class, [RecordAuthenticationAudit::class, 'recordFailure']);
        Event::listen(Registered::class, [RecordAuthenticationAudit::class, 'recordRegistration']);
    }
}
