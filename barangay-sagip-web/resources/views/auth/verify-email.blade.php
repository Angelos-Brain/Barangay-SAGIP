@extends('layouts.resident-auth')
@section('title', 'Check Your Gmail — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route($user?->isPersonnel() ? 'personnel.login' : 'login')" label="Back to sign in" />
@if ($user?->isPersonnel())
    <x-onboarding-steps :current="2" />
@endif

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">Verify Your Email</p>
<h2 class="text-2xl font-bold text-navy">Check your Gmail to verify your account</h2>

@error('link')
    <div class="mt-4 rounded-xl bg-danger/10 border border-danger/20 px-4 py-3 text-sm font-bold text-danger" role="alert">
        {{ $message }}
    </div>
@enderror

@if ($user)
    <div class="mt-4 flex items-start gap-3 rounded-xl bg-muted/60 border border-line px-4 py-3 text-sm">
        <span class="text-accent mt-px"><x-icon name="mail" /></span>
        <div>
            <p class="text-muted-fg">
                We sent a verification link to <span class="font-bold text-ink">{{ $user->email }}</span>.
                Open it to activate your account. The link works once and expires in {{ $ttlHours }} hours.
            </p>
            <p class="mt-1 text-muted-fg">Can't find it? Check your Spam or Promotions folder.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button id="resend-link" data-wait="{{ $resendIn }}"
                class="w-full min-h-12 rounded-xl border border-accent py-3 font-bold text-accent hover:bg-accent/5 transition-colors duration-200 cursor-pointer disabled:border-line disabled:text-muted-fg disabled:cursor-not-allowed disabled:hover:bg-transparent">
            Resend verification email
        </button>
    </form>
    <p id="resend-link-hint" class="mt-2 text-center text-sm text-muted-fg" aria-live="polite"></p>

    @push('scripts')
    <script>
        (function () {
            const button = document.getElementById('resend-link');
            const hint = document.getElementById('resend-link-hint');
            let wait = parseInt(button.dataset.wait, 10) || 0;

            function tick() {
                if (wait <= 0) {
                    button.disabled = false;
                    hint.textContent = '';
                    return;
                }
                button.disabled = true;
                hint.textContent = 'You can resend the email in ' + wait + ' s.';
                wait -= 1;
                setTimeout(tick, 1000);
            }

            tick();
        })();
    </script>
    @endpush
@else
    <p class="mt-1.5 text-sm text-muted-fg">
        Open the verification link we emailed you to activate your account. To get a new link, sign in again.
    </p>
    <a href="{{ route('login') }}"
       class="mt-8 w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 inline-flex items-center justify-center">
        Go to Sign In
    </a>
@endif
@endsection
