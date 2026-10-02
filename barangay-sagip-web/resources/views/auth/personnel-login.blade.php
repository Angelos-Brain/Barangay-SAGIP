@extends('layouts.resident-auth')
@section('title', 'Personnel Login — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('home')" label="Back to home" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">Response Personnel</p>
<h2 class="text-2xl font-bold text-navy">Barangay SAGIP</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">Sign in with your email and password.</p>

<form method="POST" action="{{ route('personnel.login.store') }}" class="space-y-5" novalidate>
    @csrf

    <x-auth-field label="Email" name="email" type="email" :value="old('email')"
                  placeholder="example@gmail.com" autocomplete="username" autocapitalize="none" autofocus />
    <x-auth-field label="Password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" />

    <div class="flex justify-end -mt-2">
        <a href="{{ route('personnel.password.request') }}" class="min-h-11 inline-flex items-center text-sm font-bold text-accent hover:underline">Forgot password?</a>
    </div>

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Sign In
    </button>
</form>

<div class="mt-6 rounded-xl bg-muted/60 border border-line px-4 py-3 text-sm text-muted-fg">
    <p class="font-bold text-navy">First time signing in?</p>
    <p class="mt-0.5">Your barangay admin registered your email.
        <a href="{{ route('personnel.setup') }}" class="font-bold text-accent hover:underline">Set up your account</a>
    </p>
</div>
@endsection
