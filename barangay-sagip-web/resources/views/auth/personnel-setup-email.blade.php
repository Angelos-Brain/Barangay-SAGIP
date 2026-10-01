@extends('layouts.resident-auth')
@section('title', 'Confirm Your Email — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('account.setup.password')" label="Back to password" />
<x-onboarding-steps :current="4" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">First Login</p>
<h2 class="text-2xl font-bold text-navy">Confirm your email</h2>

@if ($linkSent && ! $errors->has('email'))
    <div class="mt-4 flex items-start gap-3 rounded-xl bg-muted/60 border border-line px-4 py-3 text-sm">
        <span class="text-accent mt-px"><x-icon name="mail" /></span>
        <div>
            <p class="font-bold text-navy">Check your inbox</p>
            <p class="text-muted-fg">
                Open the link we sent to <span class="font-bold text-ink">{{ $user->email }}</span> to activate your account.
                It expires in {{ (int) config('sagip.personnel_login.email_link_ttl_hours', 24) }} hours. You can keep this page open.
            </p>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-2 text-sm">
        <form method="POST" action="{{ route('account.setup.email.resend') }}">
            @csrf
            <button id="resend-email" data-wait="{{ $resendIn }}"
                    class="min-h-11 inline-flex items-center font-bold text-accent hover:underline cursor-pointer disabled:text-muted-fg disabled:no-underline disabled:cursor-not-allowed">
                Resend link
            </button>
        </form>
        <a href="{{ route('account.setup') }}" class="min-h-11 inline-flex items-center font-bold text-accent hover:underline">I've confirmed it</a>
    </div>
    <p id="resend-email-hint" class="text-sm text-muted-fg" aria-live="polite"></p>

    <details class="mt-6 text-sm">
        <summary class="min-h-11 inline-flex items-center font-bold text-navy cursor-pointer">Use a different email</summary>
        <form method="POST" action="{{ route('account.setup.email.store') }}" class="mt-3 space-y-4" novalidate>
            @csrf
            <x-auth-field label="Email" name="email" type="email" :value="old('email', $user->email)" autocomplete="email" />
            <button class="w-full min-h-12 rounded-xl border border-accent py-3 font-bold text-accent hover:bg-accent/5 transition-colors duration-200 cursor-pointer">
                Send Link to This Email
            </button>
        </form>
    </details>

    @push('scripts')
    <script>
        (function () {
            const button = document.getElementById('resend-email');
            const hint = document.getElementById('resend-email-hint');
            let wait = parseInt(button.dataset.wait, 10) || 0;

            function tick() {
                if (wait <= 0) {
                    button.disabled = false;
                    hint.textContent = '';
                    return;
                }
                button.disabled = true;
                hint.textContent = 'You can resend the link in ' + wait + ' s.';
                wait -= 1;
                setTimeout(tick, 1000);
            }

            tick();
        })();
    </script>
    @endpush
@else
    <p class="mt-1.5 text-sm text-muted-fg mb-8">
        We'll email you a link to confirm this address. Your account becomes active once you open it.
        @if ($user->email) Your barangay admin entered this address — correct it if needed. @endif
    </p>

    <form method="POST" action="{{ route('account.setup.email.store') }}" class="space-y-5" novalidate>
        @csrf

        <x-auth-field label="Email" name="email" type="email" :value="old('email', $user->email)"
                      placeholder="you@example.com" autocomplete="email" autofocus />

        <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
            Send Confirmation Link
        </button>
    </form>
@endif
@endsection
