@extends('layouts.resident-auth')
@section('title', 'Choose a Password — Barangay SAGIP')

@section('content')
{{-- Going back past a verified phone means signing out and starting over. --}}
<x-auth-back-link :action="route('admin.logout')" label="Back (sign out)" />
<x-onboarding-steps :current="3" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">First Login</p>
<h2 class="text-2xl font-bold text-navy">Choose a password</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">
    Welcome, {{ $user->name }}. Your mobile number is verified. From now on you'll sign in with this password.
</p>

<form method="POST" action="{{ route('account.setup.password.store') }}" class="space-y-5" novalidate>
    @csrf

    <x-password-fields />

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Save and Continue
    </button>
</form>
@endsection
