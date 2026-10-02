@extends('layouts.resident-auth')
@section('title', 'Choose a New Password — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('personnel.login')" label="Cancel and go back to sign in" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">Forgot Password</p>
<h2 class="text-2xl font-bold text-navy">Choose a new password</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">Your reset link is confirmed. Choose a password with at least 8 characters.</p>

<form method="POST" action="{{ route('personnel.password.update') }}" class="space-y-5" novalidate>
    @csrf

    <x-password-fields />

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Save New Password
    </button>
</form>
@endsection
