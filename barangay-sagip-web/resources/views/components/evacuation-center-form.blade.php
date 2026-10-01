{{-- Feature 4: shared create/edit form for an evacuation center. --}}
@props([
    'center' => null,
    'statuses' => [],
    'action',
    'method' => 'POST',
    'submitLabel' => 'Save Center',
])
<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label class="block text-sm font-medium mb-1">Center Name</label>
        <input type="text" name="name" value="{{ old('name', $center->name ?? '') }}" required maxlength="255"
               placeholder="e.g. Virac Central Elementary School"
               class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Address</label>
        <input type="text" name="address" value="{{ old('address', $center->address ?? '') }}" maxlength="500"
               class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Capacity</label>
            <input type="number" name="capacity" min="1" max="100000" required
                   value="{{ old('capacity', $center->capacity ?? '') }}"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Current Occupancy</label>
            <input type="number" name="current_occupancy" min="0" max="100000" required
                   value="{{ old('current_occupancy', $center->current_occupancy ?? 0) }}"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select name="status" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('status', $center->status?->value ?? 'open') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Contact Person</label>
            <input type="text" name="contact_person" value="{{ old('contact_person', $center->contact_person ?? '') }}"
                   maxlength="255"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Contact Number</label>
            <input type="text" name="contact_number" value="{{ old('contact_number', $center->contact_number ?? '') }}"
                   maxlength="30"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Notes</label>
        <textarea name="notes" rows="2" maxlength="1000"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-accent focus:ring-accent">{{ old('notes', $center->notes ?? '') }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Location (tap the map)</label>
        <div id="center-map" class="w-full h-56 rounded-md border"></div>
        <input type="hidden" name="latitude" id="latitude" required
               value="{{ old('latitude', $center->latitude ?? '') }}">
        <input type="hidden" name="longitude" id="longitude" required
               value="{{ old('longitude', $center->longitude ?? '') }}">
        <p class="text-xs text-gray-400 mt-1">
            Selected: <span id="coords">{{ $center?->latitude ? $center->latitude.', '.$center->longitude : 'none yet' }}</span>
        </p>
    </div>

    <div class="flex items-center gap-3">
        <button class="bg-navy text-white rounded-md px-6 py-2.5 sm:py-2 text-sm font-medium hover:bg-accent transition">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('evacuation-centers.index') }}" class="text-sm text-gray-500 hover:underline">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const coords = document.getElementById('coords');

        const existing = latInput.value && lngInput.value
            ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
            : null;

        const map = L.map('center-map').setView(
            existing ?? [{{ config('sagip.hall.latitude') }}, {{ config('sagip.hall.longitude') }}],
            existing ? 16 : 14
        );
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker = existing ? L.marker(existing).addTo(map) : null;

        map.on('click', function (event) {
            latInput.value = event.latlng.lat.toFixed(7);
            lngInput.value = event.latlng.lng.toFixed(7);
            coords.textContent = latInput.value + ', ' + lngInput.value;

            if (marker) {
                marker.setLatLng(event.latlng);
            } else {
                marker = L.marker(event.latlng).addTo(map);
            }
        });
    })();
</script>
@endpush
