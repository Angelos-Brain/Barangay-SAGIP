@extends('layouts.resident-auth')
@section('title', 'Forgot Password — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('personnel.login')" label="Back to sign in" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">Forgot Password</p>
<h2 class="text-2xl font-bold text-navy">Reset your password</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">
    Enter your registered email. We'll email you a link so you can choose a new password.
</p>

<form method="POST" action="{{ route('personnel.password.send') }}" class="space-y-5" novalidate>
    @csrf

    <x-auth-field label="Email" name="email" type="email" :value="old('email')"
                  placeholder="example@gmail.com" autocomplete="email" autocapitalize="none" autofocus />

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Send Reset Link
    </button>
</form>
@endsection
