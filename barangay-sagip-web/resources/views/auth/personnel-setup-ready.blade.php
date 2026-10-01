@extends('layouts.resident-auth')
@section('title', 'Account Ready — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('home')" label="Back to home" />
<x-onboarding-steps :current="5" />

<div class="text-center">
    <span class="mx-auto h-14 w-14 rounded-full bg-success/10 text-success inline-flex items-center justify-center">
        <x-icon name="check-circle" class="h-7 w-7" />
    </span>
    <h2 class="mt-4 text-2xl font-bold text-navy">Your account is ready</h2>
    <p class="mt-1.5 text-sm text-muted-fg">
        Thanks, {{ $user->name }}. Your email is confirmed. Next time, sign in with your email or mobile number and your password.
    </p>
</div>

@if ($signedIn)
    <a href="{{ route('dashboard') }}"
       class="mt-8 w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 inline-flex items-center justify-center gap-2">
        Go to Dashboard <x-icon name="arrow-right" />
    </a>
@else
    <a href="{{ route('personnel.login') }}"
       class="mt-8 w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 inline-flex items-center justify-center gap-2">
        Sign In <x-icon name="arrow-right" />
    </a>
@endif
@endsection
