@extends('layouts.app')
@section('title', 'Submit a Request — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    @keyframes radar-ping {
        0% { transform: scale(0.95); opacity: 0.8; }
        50% { transform: scale(1.3); opacity: 0.2; }
        100% { transform: scale(0.95); opacity: 0.8; }
    }
    .animate-radar {
        animation: radar-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
    }
</style>
@endpush

@section('content')
<div class="max-w-2xl mx-auto">

    @unless(auth()->user()->residentProfile)
        <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-md p-3 mb-4">
            Tip: <a href="{{ route('residents.profile.edit') }}" class="underline font-medium">complete your resident profile</a>
            so responders can identify and reach you faster.
        </div>
    @endunless

    <div class="bg-surface p-4 sm:p-6 rounded-lg shadow">
        <h1 class="text-xl font-bold text-navy mb-1">Submit an Emergency / Assistance Request</h1>
        <p class="text-sm text-gray-500 mb-6">
            Describe what's happening in your own words — our system will automatically classify the type
            and urgency of your request and route it to the right responder.
        </p>

        <form method="POST" action="{{ route('requests.store') }}" class="space-y-4" id="request-form">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">What's happening?</label>
                <textarea name="description" rows="4" required minlength="5" maxlength="2000"
                          placeholder="e.g. Sunog po sa bahay namin, malaki na ang apoy..."
                          class="w-full rounded-md border border-gray-300 px-3 py-2 sm:border-0 sm:p-0 shadow-sm focus:border-accent focus:ring-accent">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Your Current Location</label>

                <div class="relative w-full h-64 sm:h-72 rounded-xl overflow-hidden border border-gray-300 shadow-inner bg-gray-900">
                    <div id="gps-loading-overlay" class="absolute inset-0 z-30 bg-gray-900/90 backdrop-blur-sm flex flex-col items-center justify-center text-white p-6 transition-opacity duration-500">
                        <div class="relative w-16 h-16 mb-4 flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full border-2 border-indigo-500/30 animate-radar"></div>
                            <div class="absolute inset-0 rounded-full border-2 border-t-indigo-500 border-r-transparent border-b-indigo-500/50 border-l-transparent animate-spin"></div>
                            <svg class="w-6 h-6 text-indigo-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div id="loading-title" class="text-center text-sm font-mono tracking-widest uppercase text-indigo-300 animate-pulse">Requesting GPS Permission...</div>
                        <div id="loading-subtitle" class="text-center text-xs text-slate-400 mt-1">Allow location access so we can automatically detect your current position.</div>
                    </div>

                    {{-- `relative z-0` keeps Leaflet's own pane z-indexes below the overlays. --}}
                    <div id="location-map" class="relative z-0 w-full h-full" role="application"
                         aria-label="Map of your location. Drag the pin or tap the map to set your exact spot."></div>

                    <div class="pointer-events-none absolute bottom-3 left-3 right-3 z-20 bg-surface/95 backdrop-blur-md p-3 rounded-lg shadow-lg border border-gray-100 flex items-center space-x-3">
                        <div class="bg-indigo-600 text-white p-2 rounded-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Live Location</p>
                            <p id="address-display" class="text-xs text-gray-700 truncate">Waiting for GPS permission...</p>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}" required>
                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}" required>

                <div class="flex flex-col items-start sm:flex-row sm:items-center sm:justify-between mt-2 gap-2 sm:gap-4">
                    <p id="coords-label" class="text-xs text-gray-500">
                        Your browser will ask for GPS permission. Your current device location is tracked while this report form is open.
                    </p>
                    <button type="button" id="retry-location" class="py-2 sm:py-0 text-sm sm:text-xs text-indigo-600 hover:underline font-medium whitespace-nowrap hidden">
                        Retry GPS Location
                    </button>
                </div>

                <div id="gps-error" class="hidden mt-2 rounded-md bg-red-50 border border-red-200 text-red-700 text-xs p-3"></div>
                <div id="gps-warning" class="hidden mt-2 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-xs p-3"></div>

                <div class="mt-3">
                    <label for="manual-coordinates" class="block text-xs font-medium text-gray-600 mb-1">
                        Or enter your coordinates
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="manual-coordinates" autocomplete="off"
                               placeholder="e.g. 13°35'36.9&quot;N 124°12'22.5&quot;E or 13.593583, 124.206250"
                               class="flex-1 min-w-0 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <button type="button" id="apply-coordinates"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-navy hover:bg-gray-50">
                            Set pin
                        </button>
                    </div>
                </div>
            </div>

            <button id="submit-request" type="submit"
                    class="w-full bg-navy text-white rounded-md py-3 sm:py-2 font-medium hover:bg-accent transition disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                Waiting for GPS Location...
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('request-form');
        const submitButton = document.getElementById('submit-request');
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        const gpsWarning = document.getElementById('gps-warning');
        const manualCoordinates = document.getElementById('manual-coordinates');
        const applyCoordinates = document.getElementById('apply-coordinates');

        // A laptop or PC without a GPS chip reports a location guessed from its
        // internet connection, which can be hundreds of kilometres off. Readings
        // like that are caught here and the resident places the pin instead.
        const hall = @js(['lat' => (float) config('sagip.hall.latitude'), 'lng' => (float) config('sagip.hall.longitude')]);
        const poorAccuracyMeters = @js((int) config('sagip.sos.poor_accuracy_meters', 100));
        const boundsRadiusMeters = @js((int) config('sagip.sos.bounds_radius_meters', 3000));
        const coordsLabel = document.getElementById('coords-label');
        const retryButton = document.getElementById('retry-location');
        const loadingOverlay = document.getElementById('gps-loading-overlay');
        const addressDisplay = document.getElementById('address-display');
        const loadingTitle = document.getElementById('loading-title');
        const loadingSubtitle = document.getElementById('loading-subtitle');
        const gpsError = document.getElementById('gps-error');

        let watchId = null;
        let latestPosition = null;
        let pinnedManually = false;

        const map = L.map('location-map').setView([hall.lat, hall.lng], 16);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            // Esri has no imagery past zoom 18 here and serves "Map data not yet available"
            // tiles instead; stretching zoom-18 tiles keeps the closest zoom level usable.
            maxNativeZoom: 18,
            maxZoom: 19,
            attribution: 'Imagery &copy; Esri',
        }).addTo(map);

        const marker = L.marker([hall.lat, hall.lng], { draggable: true, keyboard: true, title: 'Your location' });
        const accuracyCircle = L.circle([hall.lat, hall.lng], {
            radius: 0, color: '#4f46e5', weight: 1, fillOpacity: 0.12, interactive: false,
        });

        function setGpsWarning(message) {
            gpsWarning.textContent = message;
            gpsWarning.classList.toggle('hidden', !message);
        }

        function formatDistance(meters) {
            return meters >= 1000 ? `${(meters / 1000).toFixed(meters >= 10000 ? 0 : 1)} km` : `${Math.round(meters)} m`;
        }

        function enableSubmit(enabled, label) {
            submitButton.disabled = !enabled;
            submitButton.textContent = label;
        }

        function setLocation(lat, lng) {
            latitudeInput.value = lat.toFixed(7);
            longitudeInput.value = lng.toFixed(7);
            addressDisplay.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            marker.setLatLng([lat, lng]);

            if (!map.hasLayer(marker)) {
                marker.addTo(map);
            }
        }

        // The resident's own pin always wins over later GPS readings.
        function pinManually(lat, lng) {
            pinnedManually = true;
            stopWatching();
            map.removeLayer(accuracyCircle);
            setLocation(lat, lng);
            map.setView([lat, lng], Math.max(map.getZoom(), 17));
            clearGpsError();
            hideOverlay();

            const fromHall = map.distance([hall.lat, hall.lng], [lat, lng]);
            setGpsWarning(fromHall > boundsRadiusMeters
                ? `This pin is ${formatDistance(fromHall)} from the barangay hall, outside the barangay. Double-check it before submitting.`
                : '');

            coordsLabel.textContent = 'Location set by you. Drag the pin or tap the map to adjust it.';
            retryButton.textContent = 'Use device GPS again';
            retryButton.classList.remove('hidden');
            enableSubmit(true, 'Submit Request');
        }

        marker.on('dragend', () => {
            const { lat, lng } = marker.getLatLng();
            pinManually(lat, lng);
        });

        map.on('click', (event) => pinManually(event.latlng.lat, event.latlng.lng));

        /**
         * Accepts decimal ("13.5936, 124.2063") or degrees-minutes-seconds
         * (13°35'36.9"N 124°12'22.5"E) coordinates.
         */
        function parseCoordinates(text) {
            const dms = /(\d+(?:\.\d+)?)\s*°\s*(?:(\d+(?:\.\d+)?)\s*['′]\s*)?(?:(\d+(?:\.\d+)?)\s*["″]\s*)?([NSEW])/gi;
            const parts = [...text.matchAll(dms)];

            if (parts.length === 2) {
                const values = {};

                for (const [, deg, min = 0, sec = 0, hemi] of parts) {
                    const h = hemi.toUpperCase();
                    const value = Number(deg) + Number(min) / 60 + Number(sec) / 3600;
                    values[h === 'N' || h === 'S' ? 'lat' : 'lng'] = h === 'S' || h === 'W' ? -value : value;
                }

                return values.lat !== undefined && values.lng !== undefined ? values : null;
            }

            const decimal = text.match(/(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)/);

            return decimal ? { lat: Number(decimal[1]), lng: Number(decimal[2]) } : null;
        }

        function applyManualCoordinates() {
            const parsed = parseCoordinates(manualCoordinates.value);

            if (!parsed || Math.abs(parsed.lat) > 90 || Math.abs(parsed.lng) > 180) {
                setGpsError('Those coordinates could not be read. Use a format like 13.593583, 124.206250.');
                return;
            }

            pinManually(parsed.lat, parsed.lng);
        }

        applyCoordinates.addEventListener('click', applyManualCoordinates);
        manualCoordinates.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyManualCoordinates();
            }
        });

        function setGpsError(message) {
            gpsError.textContent = message;
            gpsError.classList.remove('hidden');
        }

        function clearGpsError() {
            gpsError.textContent = '';
            gpsError.classList.add('hidden');
        }

        function showOverlay(title, subtitle) {
            loadingTitle.textContent = title;
            loadingSubtitle.textContent = subtitle;
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.opacity = '1';
        }

        function hideOverlay() {
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 400);
        }

        function stopWatching() {
            if (watchId !== null && navigator.geolocation) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        }

        function handlePosition(position) {
            if (pinnedManually) {
                return;
            }

            latestPosition = position;

            const { latitude, longitude, accuracy } = position.coords;
            const fromHall = map.distance([hall.lat, hall.lng], [latitude, longitude]);

            clearGpsError();
            hideOverlay();
            retryButton.classList.add('hidden');

            // Kilometres outside the barangay: the device is guessing from its
            // internet connection. Don't submit that; ask for the pin instead.
            if (fromHall > boundsRadiusMeters) {
                latitudeInput.value = '';
                longitudeInput.value = '';
                addressDisplay.textContent = 'Set your location on the map';
                map.removeLayer(marker);
                map.removeLayer(accuracyCircle);
                map.setView([hall.lat, hall.lng], 16);

                setGpsWarning(
                    `Your device reported a location ${formatDistance(fromHall)} away from the barangay ` +
                    `(${latitude.toFixed(4)}, ${longitude.toFixed(4)}, accuracy about ${formatDistance(accuracy)}). ` +
                    `Computers without GPS often guess their location from the internet connection. ` +
                    `Tap the map where you are, drag the pin, or enter your coordinates below.`
                );
                coordsLabel.textContent = 'Your exact location is required before you can submit.';
                enableSubmit(false, 'Set Your Location on the Map');
                return;
            }

            setLocation(latitude, longitude);
            accuracyCircle.setLatLng([latitude, longitude]).setRadius(accuracy);

            if (!map.hasLayer(accuracyCircle)) {
                accuracyCircle.addTo(map);
            }

            map.setView([latitude, longitude], accuracy > poorAccuracyMeters ? 16 : 18);

            setGpsWarning(accuracy > poorAccuracyMeters
                ? `Your device's location is only accurate to about ${formatDistance(accuracy)}. ` +
                  `If the pin is not where you are, drag it or tap your exact spot on the map.`
                : '');

            coordsLabel.textContent =
                `Live GPS location acquired. Accuracy: approximately ${formatDistance(accuracy)}. ` +
                `It updates automatically until you move the pin yourself.`;

            enableSubmit(true, 'Submit Request');
        }

        function handleError(error) {
            if (pinnedManually) {
                return;
            }

            latestPosition = null;
            latitudeInput.value = '';
            longitudeInput.value = '';

            const messages = {
                1: 'Location permission was denied. Please allow location access for Barangay SAGIP in your browser settings, then retry.',
                2: 'Your device could not determine a location. Turn on Location/GPS services and try again.',
                3: 'GPS detection timed out. Make sure Location/GPS is enabled and try again.'
            };

            const message = messages[error.code] || 'Unable to determine your current device location.';
            setGpsError(message);

            showOverlay(
                error.code === 1 ? 'GPS Permission Required' : 'GPS Location Unavailable',
                message
            );

            addressDisplay.textContent = 'Current location not available';
            coordsLabel.textContent = 'Allow GPS, or set your location by tapping the map or entering your coordinates.';
            hideOverlay();
            submitButton.disabled = true;
            submitButton.textContent = 'Location Required';
            retryButton.classList.remove('hidden');
        }

        function requestGps() {
            clearGpsError();
            setGpsWarning('');
            pinnedManually = false;
            retryButton.textContent = 'Retry GPS Location';

            if (!window.isSecureContext) {
                setGpsError('GPS access requires a secure HTTPS connection. Open Barangay SAGIP through its HTTPS Herd address.');
                addressDisplay.textContent = 'Secure HTTPS connection required';
                coordsLabel.textContent = 'Open https://barangay-sagip.test and try again.';
                submitButton.disabled = true;
                submitButton.textContent = 'HTTPS Required';
                retryButton.classList.remove('hidden');
                return;
            }

            if (!navigator.geolocation) {
                setGpsError('This browser does not support device geolocation.');
                addressDisplay.textContent = 'Geolocation not supported';
                submitButton.disabled = true;
                submitButton.textContent = 'GPS Not Supported';
                retryButton.classList.remove('hidden');
                return;
            }

            showOverlay(
                'Requesting GPS Permission...',
                'Allow location access so we can automatically detect and track your current position.'
            );
            retryButton.classList.add('hidden');

            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }

            watchId = navigator.geolocation.watchPosition(
                handlePosition,
                handleError,
                {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 0
                }
            );
        }

        retryButton.addEventListener('click', requestGps);

        form.addEventListener('submit', (event) => {
            if (!latitudeInput.value || !longitudeInput.value) {
                event.preventDefault();

                if (!pinnedManually && !latestPosition) {
                    requestGps();
                }

                return;
            }

            stopWatching();
        });

        window.addEventListener('pagehide', stopWatching);

        requestGps();
    })();
</script>
@endpush
@endsection
