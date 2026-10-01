@extends('layouts.resident-auth')
@section('title', 'Sign In — Barangay SAGIP')

@section('content')
<h2 class="text-2xl font-bold text-navy">Welcome back</h2>
<p class="mt-1.5 text-sm text-muted-fg mb-8">Sign in to report an emergency or check your requests.</p>

<form method="POST" action="{{ route('login') }}" class="space-y-5">
    @csrf

    <x-auth-field label="Email" name="email" type="email" :value="old('email')" placeholder="you@example.com" autofocus />
    <x-auth-field label="Password" name="password" type="password" placeholder="Enter your password" />

    <label class="flex items-center gap-2.5 min-h-11 text-sm text-muted-fg cursor-pointer">
        <input type="checkbox" name="remember" class="h-5 w-5 rounded accent-accent cursor-pointer">
        Remember me
    </label>

    <button class="w-full min-h-12 rounded-xl bg-accent py-3 font-bold text-white shadow-card hover:opacity-90 transition-opacity duration-200 cursor-pointer">
        Sign In
    </button>
</form>

<p class="mt-8 text-center text-sm text-muted-fg">
    New to Barangay SAGIP?
    <a href="{{ route('register') }}" class="font-bold text-accent hover:underline">
        Create an account
    </a>
</p>
@endsection
