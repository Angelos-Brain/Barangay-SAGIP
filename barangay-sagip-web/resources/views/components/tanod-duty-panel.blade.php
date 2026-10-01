{{--
    Feature 7: Tanod location lock.

    A tanod goes on duty by checking in at the barangay hall. The browser reads
    the live GPS fix, the form posts it, and the server measures the distance —
    the client-side distance readout below is a courtesy so the tanod knows
    whether walking closer will help, never the thing that decides.
--}}
@props(['personnel'])
@php
    $hall = ['latitude' => (float) config('sagip.hall.latitude'), 'longitude' => (float) config('sagip.hall.longitude')];
    $radius = (int) config('sagip.tanod.check_in_radius_meters');
    $onDuty = $personnel->on_duty_at !== null;
@endphp
<div class="bg-surface rounded-lg shadow p-4 mb-6"
     x-data="tanodDuty({ hall: @js($hall), radius: {{ $radius }} })">

    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="font-semibold text-navy text-sm">Tanod Duty Status</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                @if($onDuty)
                    On duty since {{ $personnel->on_duty_at->format('M j, g:i A') }}
                    @if($personnel->last_check_in_distance_meters !== null)
                        · checked in {{ number_format((float) $personnel->last_check_in_distance_meters) }}m from the hall
                    @endif
                @else
                    You must check in within {{ number_format($radius) }}m of the barangay hall to go on duty.
                @endif
            </p>
        </div>
        <x-badge :color="$onDuty ? 'green' : 'gray'">{{ $onDuty ? 'On duty' : 'Off duty' }}</x-badge>
    </div>

    <div class="mt-3 text-xs" x-show="reading || distance !== null" x-cloak>
        <p x-show="reading" class="text-gray-500">Reading your location...</p>
        <p x-show="distance !== null" x-cloak
           :class="withinRadius ? 'text-green-700' : 'text-red-700'">
            You are <span x-text="Math.round(distance)"></span>m from the barangay hall.
            <span x-show="!withinRadius" x-cloak>Walk closer to check in.</span>
        </p>
        <p x-show="error" x-cloak class="text-red-700" x-text="error"></p>
    </div>

    @if($onDuty)
        <form method="POST" action="{{ route('tanod.checkOut') }}" class="mt-4 space-y-2">
            @csrf
            <label for="tanod_reason" class="block text-xs font-medium text-gray-700">
                Reason for going off duty <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="text" name="reason" id="tanod_reason" maxlength="500"
                   class="w-full sm:w-80 rounded-md border-gray-300 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <button class="block bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-md px-4 py-2">
                Check out (go off duty)
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('tanod.checkIn') }}" class="mt-4" @submit="capture">
            @csrf
            <input type="hidden" name="latitude" x-ref="latitude">
            <input type="hidden" name="longitude" x-ref="longitude">
            <button type="button" @click="checkIn($el.closest('form'))" :disabled="reading"
                    class="bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-wait
                           text-white text-sm font-medium rounded-md px-4 py-2">
                <span x-text="reading ? 'Reading GPS...' : 'Check in at the barangay hall'"></span>
            </button>
        </form>
    @endif
</div>

@once
    @push('scripts')
    <script>
        function tanodDuty(config) {
            return {
                reading: false,
                distance: null,
                withinRadius: false,
                error: null,

                /** Haversine, in metres — matches App\Support\Geo. */
                metresFromHall(latitude, longitude) {
                    const radius = 6371000;
                    const toRad = degrees => degrees * Math.PI / 180;
                    const dLat = toRad(latitude - config.hall.latitude);
                    const dLng = toRad(longitude - config.hall.longitude);
                    const a = Math.sin(dLat / 2) ** 2
                        + Math.cos(toRad(config.hall.latitude)) * Math.cos(toRad(latitude))
                        * Math.sin(dLng / 2) ** 2;
                    return radius * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
                },

                checkIn(form) {
                    this.error = null;

                    if (!navigator.geolocation) {
                        this.error = 'This device cannot report its location. Ask an official to check you in.';
                        return;
                    }

                    this.reading = true;

                    navigator.geolocation.getCurrentPosition(
                        position => {
                            this.reading = false;
                            const { latitude, longitude } = position.coords;

                            this.distance = this.metresFromHall(latitude, longitude);
                            this.withinRadius = this.distance <= config.radius;

                            this.$refs.latitude.value = latitude.toFixed(7);
                            this.$refs.longitude.value = longitude.toFixed(7);

                            // The server decides; submitting an out-of-range fix
                            // is what produces the flagged, auditable rejection.
                            form.submit();
                        },
                        () => {
                            this.reading = false;
                            this.error = 'Turn on GPS and allow location access, then try again.';
                        },
                        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                    );
                },
            };
        }
    </script>
    @endpush
@endonce
