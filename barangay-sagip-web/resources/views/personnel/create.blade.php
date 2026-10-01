@extends('layouts.app')
@section('title', 'Add Personnel — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@section('content')
<div class="max-w-lg mx-auto bg-surface rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl font-bold text-navy mb-4">Add Response Personnel</h1>

    <form method="POST" action="{{ route('personnel.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input type="text" name="name" required class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
        </div>
        <div>
            <x-specialization-picker :specializations="$specializations" />
        </div>
        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone_number" class="block text-sm font-medium mb-1">Mobile Number</label>
            <input type="tel" name="phone_number" id="phone_number" value="{{ old('phone_number') }}" required
                   placeholder="09XXXXXXXXX" inputmode="tel"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            <p class="text-xs text-gray-500 mt-1">
                This number is their login. They sign in at <span class="font-mono">/personnel/login</span> with a code texted to it — no password needed.
            </p>
            @error('phone_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Base Location (tap map)</label>
            <div id="map" class="w-full h-56 rounded-md border"></div>
            <input type="hidden" name="latitude" id="latitude" required>
            <input type="hidden" name="longitude" id="longitude" required>
        </div>
        <button class="w-full sm:w-auto bg-navy text-white rounded-md px-6 py-2.5 sm:py-2 text-sm font-medium hover:bg-accent transition">
            Save Personnel
        </button>
    </form>
</div>

@push('scripts')
<script>
    const map = L.map('map').setView([13.5925, 124.2049], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    let marker = null;
    map.on('click', function (e) {
        document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
        if (marker) { marker.setLatLng(e.latlng); } else { marker = L.marker(e.latlng).addTo(map); }
    });
</script>
@endpush
@endsection
