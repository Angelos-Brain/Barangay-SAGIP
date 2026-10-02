@extends('layouts.app')
@section('title', 'My Profile — Barangay SAGIP')

@section('content')
<div class="max-w-2xl mx-auto bg-surface p-4 sm:p-6 rounded-lg shadow">
    <h1 class="text-xl font-bold text-navy mb-1">Resident Profile</h1>
    <p class="text-sm text-gray-500 mb-6">
        Keeping this up to date helps responders find and identify you faster during an emergency.
    </p>

    <x-photo-upload :user="auth()->user()" :action="route('account.photo.update')" class="space-y-3 pb-6 mb-6 border-b" />

    <form method="POST" action="{{ route('residents.profile.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">Full Name</label>
                <input type="text" name="full_name" value="{{ old('full_name', $profile->full_name ?? '') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Birthdate</label>
                <input type="date" name="birthdate" value="{{ old('birthdate', optional($profile->birthdate ?? null)->format('Y-m-d')) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Sex</label>
                <select name="sex" required class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
                    <option value="" disabled @selected(old('sex', $profile->sex ?? '') === '')>Select sex</option>
                    <option value="male" @selected(old('sex', $profile->sex ?? '') === 'male')>Male</option>
                    <option value="female" @selected(old('sex', $profile->sex ?? '') === 'female')>Female</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Civil Status</label>
                <select name="civil_status" required class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
                    <option value="" disabled @selected(old('civil_status', $profile->civil_status ?? '') === '')>Select civil status</option>
                    @foreach (['single','married','widowed','separated'] as $status)
                        <option value="{{ $status }}" @selected(old('civil_status', $profile->civil_status ?? '') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Purok / Sitio</label>
                <input type="text" name="purok_sitio" value="{{ old('purok_sitio', $profile->purok_sitio ?? '') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">Complete Home Address</label>
                <textarea name="address" rows="3" required maxlength="500"
                          placeholder="e.g. 123, Sample Street, Calatagan Tibang, Virac, Catanduanes"
                          class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">{{ old('address', $profile->address ?? '') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    Format: House/Unit Number, Street/Road, Barangay, Municipality/City, Province
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Household Members</label>
                <input type="number" name="household_members_count" min="1" max="50"
                       value="{{ old('household_members_count', $profile->household_members_count ?? 1) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>

            <div class="sm:col-span-2">
                <x-vulnerability-picker :tags="\App\Enums\VulnerabilityTag::options()"
                                        :selected="$profile?->vulnerabilityTagValues() ?? []" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name ?? '') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">Emergency Contact Number</label>
                <input type="text" name="emergency_contact_number" value="{{ old('emergency_contact_number', $profile->emergency_contact_number ?? '') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            </div>
        </div>

        <button class="w-full sm:w-auto bg-navy text-white rounded-md px-6 py-2.5 sm:py-2 font-medium hover:bg-accent transition">
            Save Profile
        </button>
    </form>
</div>
@endsection
