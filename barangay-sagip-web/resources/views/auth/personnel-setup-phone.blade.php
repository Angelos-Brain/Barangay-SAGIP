@extends('layouts.resident-auth')
@section('title', 'Set Up Your Account — Barangay SAGIP')

@section('content')
<x-auth-back-link :href="route('personnel.login')" label="Back to sign in" />
<x-onboarding-steps :current="1" />

<p class="text-xs font-bold tracking-wider text-accent uppercase mb-1">First Login</p>
<h2 class="text-2xl font-bold text-navy">Set up your responder account</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">
    Enter the mobile number your barangay admin registered for you. We'll text you a one-time code to confirm it's yours.
</p>

<form method="POST" action="{{ route('personnel.setup.send') }}" class="space-y-5" novalidate>
    @csrf

    <x-auth-field label="Mobile Number" name="phone_number" type="tel" :value="old('phone_number', $phone)"
                  placeholder="09XXXXXXXXX" inputmode="tel" autocomplete="tel" autofocus />

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Send Verification Code
    </button>
</form>

<p class="mt-6 text-center text-sm text-muted-fg">
    Already set up?
    <a href="{{ route('personnel.login') }}" class="font-bold text-accent hover:underline">Sign in</a>
</p>
@endsection
