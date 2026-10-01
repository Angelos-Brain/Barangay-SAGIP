@extends('layouts.app')
@section('title', 'Edit Personnel — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@section('content')
<div class="max-w-lg mx-auto bg-surface rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl font-bold text-navy mb-4">Edit {{ $personnel->name }}</h1>

    @if($personnel->user)
        <x-photo-upload :user="$personnel->user" :action="route('personnel.photo.update', $personnel)" class="space-y-3 pb-6 mb-6 border-b" />
    @else
        <p class="text-sm text-gray-500 bg-gray-50 border rounded-md px-3 py-2 mb-6">
            A photo can be added once this record is linked to a personnel login account.
        </p>
    @endif

    <form method="POST" action="{{ route('personnel.update', $personnel) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $personnel->name) }}" required
                   class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
        </div>

        <div>
            <x-specialization-picker :specializations="$specializations"
                                     :selected="$personnel->specializationValues()" />
        </div>

        <div>
            <label for="phone_number" class="block text-sm font-medium mb-1">{{ $personnel->user ? 'Mobile Number' : 'Phone Number' }}</label>
            <input type="tel" name="phone_number" id="phone_number" value="{{ old('phone_number', $personnel->user?->phone_number ?? $personnel->phone_number) }}"
                   @if($personnel->user) required placeholder="09XXXXXXXXX" @endif
                   class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">
            @if($personnel->user)
                <p class="text-xs text-gray-500 mt-1">Changing this changes the number they sign in with.</p>
            @endif
            @error('phone_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_available" value="0">
            <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $personnel->is_available)) class="rounded border-gray-300">
            Available for assignment
        </label>

        <div>
            <label class="block text-sm font-medium mb-1">Base Location (tap map to update)</label>
            <div id="map" class="w-full h-56 rounded-md border"></div>
            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $personnel->latitude) }}" required>
            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $personnel->longitude) }}" required>
            <p class="text-xs text-gray-400 mt-1">Current: {{ $personnel->latitude }}, {{ $personnel->longitude }}</p>
        </div>

        <div class="flex gap-2">
            <button class="flex-1 sm:flex-none bg-navy text-white rounded-md px-6 py-2.5 sm:py-2 text-sm font-medium hover:bg-accent transition">
                Save Changes
            </button>
            <a href="{{ route('personnel.index') }}" class="flex-1 sm:flex-none text-center border border-gray-300 rounded-md px-6 py-2.5 sm:py-2 text-sm font-medium hover:bg-gray-50 transition">
                Cancel
            </a>
        </div>
    </form>

    <form method="POST" action="{{ route('personnel.destroy', $personnel) }}"
          onsubmit="return confirm('Remove {{ $personnel->name }}? This can\'t be undone.');"
          class="mt-4 pt-4 border-t">
        @csrf
        @method('DELETE')
        <button class="py-2 sm:py-0 text-red-600 text-sm hover:underline">Delete this personnel record</button>
    </form>
</div>

@push('scripts')
<script>
    const startLat = {{ old('latitude', $personnel->latitude) }};
    const startLng = {{ old('longitude', $personnel->longitude) }};
    const map = L.map('map').setView([startLat, startLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    let marker = L.marker([startLat, startLng]).addTo(map);
    map.on('click', function (e) {
        document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
        marker.setLatLng(e.latlng);
    });
</script>
@endpush
@endsection
