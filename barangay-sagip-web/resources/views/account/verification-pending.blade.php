@extends('layouts.app')
@section('title', 'Account Verification — Barangay SAGIP')

@section('content')
<div class="max-w-xl mx-auto">
    @php $status = $user->verification_status; @endphp

    <div class="bg-surface rounded-lg shadow p-6 text-center">
        <x-badge :color="$status?->badgeColor() ?? 'gray'" class="mb-3">{{ $status?->label() ?? 'Unknown' }}</x-badge>

        @if ($user->isRejected())
            <h1 class="text-xl font-bold text-navy mb-2">Your account was not approved</h1>
            <p class="text-sm text-gray-600">
                Emergency reporting is unavailable on this account. Please visit the barangay hall with a valid ID
                so an official can review your registration in person.
            </p>
            @if ($user->verification_note)
                <p class="text-sm text-gray-700 mt-3 bg-gray-50 rounded-md p-3">
                    <span class="font-medium">Note from the barangay:</span> {{ $user->verification_note }}
                </p>
            @endif
        @else
            <h1 class="text-xl font-bold text-navy mb-2">Your account is awaiting verification</h1>
            <p class="text-sm text-gray-600">
                A barangay official needs to confirm your registration before you can file an emergency report.
                You can complete your profile in the meantime — a complete profile is reviewed faster.
            </p>
        @endif

        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('residents.profile.edit') }}"
               class="bg-navy text-white text-sm rounded-md px-4 py-2 hover:bg-accent transition">
                Complete my profile
            </a>
            <a href="{{ route('notifications.index') }}" class="text-sm text-accent hover:underline">Notifications</a>
        </div>
    </div>
</div>
@endsection
