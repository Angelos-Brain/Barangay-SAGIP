{{--
    Shared SMS code screen for First Login (step 2) and Forgot Password.
    Expects: $maskedPhone, $resendIn, $checkRoute, $resendRoute, $backRoute,
    and $step (null outside First Login).
--}}
@extends('layouts.resident-auth')
@section('title', 'Enter Verification Code — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route($backRoute)" label="Use a different number" />
@if ($step)
    <x-onboarding-steps :current="$step" />
@endif

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">{{ $step ? 'First Login' : 'Forgot Password' }}</p>
<h2 class="text-2xl font-bold text-navy">Enter your code</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-6">
    We sent a {{ \App\Services\PersonnelLoginCodeService::CODE_LENGTH }}-digit code to {{ $maskedPhone }}.
    It expires in {{ (int) config('sagip.personnel_login.code_ttl_minutes', 5) }} minutes.
</p>

<form method="POST" action="{{ route($checkRoute) }}" class="space-y-5" novalidate>
    @csrf

    <x-auth-field label="Verification Code" name="code" inputmode="numeric" autocomplete="one-time-code"
                  maxlength="{{ \App\Services\PersonnelLoginCodeService::CODE_LENGTH }}"
                  pattern="[0-9]*" placeholder="123456" autofocus />

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Verify Code
    </button>
</form>

<form method="POST" action="{{ route($resendRoute) }}" class="mt-6 text-center text-sm">
    @csrf
    <button id="resend-code" data-wait="{{ $resendIn }}"
            class="min-h-11 inline-flex items-center font-bold text-accent hover:underline cursor-pointer disabled:text-muted-fg disabled:no-underline disabled:cursor-not-allowed">
        Resend code
    </button>
    <p id="resend-hint" class="text-muted-fg" aria-live="polite"></p>
</form>

@push('scripts')
<script>
    (function () {
        const button = document.getElementById('resend-code');
        const hint = document.getElementById('resend-hint');
        let wait = parseInt(button.dataset.wait, 10) || 0;

        function tick() {
            if (wait <= 0) {
                button.disabled = false;
                hint.textContent = '';
                return;
            }
            button.disabled = true;
            hint.textContent = 'You can request a new code in ' + wait + ' s.';
            wait -= 1;
            setTimeout(tick, 1000);
        }

        tick();
    })();
</script>
@endpush
@endsection
