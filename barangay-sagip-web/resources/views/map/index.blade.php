@extends('layouts.app')
@section('title', 'Live Map — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    /* Circular status pins (MASTER.md → Map card). Color comes from --pin. */
    .sg-pin {
        display: flex; align-items: center; justify-content: center;
        width: 100%; height: 100%; border-radius: 9999px;
        background: var(--pin); border: 2px solid rgb(var(--c-white)); color: rgb(var(--c-white));
        box-shadow: 0 2px 6px rgb(var(--c-shadow) / 0.35);
    }
    .sg-pin--sos { box-shadow: 0 0 0 4px color-mix(in srgb, var(--pin) 35%, transparent), 0 2px 6px rgb(var(--c-shadow) / 0.35); }
    .sg-pin svg { width: 60%; height: 60%; }
    .leaflet-popup-content-wrapper { border-radius: 12px; font-family: 'Atkinson Hyperlegible', sans-serif; }
</style>
@endpush

@section('content')
<div x-data="sagipMap()" class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-navy">Evacuation &amp; Incident Map</h1>
            <p class="text-sm text-muted-fg mt-0.5">Open requests, responders, evacuation centers{{ $canSeeHotspots ? ' and incident hotspots' : '' }} — refreshes every 15 seconds.</p>
        </div>
        <a href="{{ route('evacuation-centers.index') }}" class="inline-flex items-center gap-2 min-h-11 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-bold text-navy hover:bg-canvas transition-colors duration-200">
            <x-icon name="house" /> Evacuation center list
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_20rem] gap-6">
        {{-- Map card --}}
        <x-card title="Live Map">
            <x-slot:actions>
                <span class="inline-flex items-center gap-1.5 text-xs text-muted-fg" aria-live="polite">
                    <x-icon name="refresh" class="w-4 h-4" />
                    <span x-text="updatedAt ? 'Updated ' + updatedAt : 'Loading…'">Loading…</span>
                </span>
            </x-slot:actions>

            {{-- Legend row --}}
            <ul class="flex flex-wrap gap-2 mb-4 text-xs font-bold text-navy" aria-label="Map legend">
                @foreach ([
                    ['Critical', 'var(--color-danger)', false],
                    ['High', 'var(--color-alert)', false],
                    ['Average', 'var(--color-warning)', false],
                    ['Low', 'var(--color-success)', false],
                    ['SOS', 'var(--color-danger)', true],
                    ['Personnel', 'var(--color-accent)', false],
                ] as [$legendLabel, $legendColor, $isSos])
                    <li class="inline-flex items-center gap-2 rounded-full bg-canvas border border-line px-3 py-1.5">
                        <span class="h-3 w-3 rounded-full ring-2 ring-surface" style="background: {{ $legendColor }};{{ $isSos ? ' box-shadow: 0 0 0 4px rgb(var(--c-danger) / .3);' : '' }}"></span>
                        {{ $legendLabel }}
                    </li>
                @endforeach
                <li class="inline-flex items-center gap-2 rounded-full bg-canvas border border-line px-3 py-1.5">
                    <x-icon name="house" class="w-3.5 h-3.5 text-success" /> Evacuation center
                </li>
                @if($canSeeHotspots)
                    <li class="inline-flex items-center gap-2 rounded-full bg-canvas border border-line px-3 py-1.5">
                        <span class="h-3 w-3 rounded-full bg-danger/30 border border-danger"></span> Hotspot
                    </li>
                @endif
            </ul>

            <div id="map" class="w-full h-[60vh] min-h-[22rem] lg:h-[34rem] rounded-xl border border-line overflow-hidden"></div>
        </x-card>

        {{-- Control panel --}}
        <x-card title="Control" subtitle="Choose what the map shows.">
            <div class="divide-y divide-line -my-1">
                {{-- Feature 4: layer toggles, so a responder can strip the map back to what they need. --}}
                <x-toggle id="layer-requests" checked label="Requests" dot="var(--color-danger)" hint="Open incidents, colored by urgency" />
                <x-toggle id="layer-personnel" checked label="Personnel" dot="var(--color-accent)" hint="Available responders" />
                <x-toggle id="layer-centers" checked label="Evacuation centers" dot="var(--color-success)" hint="Pin color = center status" />
                @if($canSeeHotspots)
                    <x-toggle id="layer-hotspots" checked label="Hotspots" dot="var(--color-alert)" hint="Weighted toward recent incidents" />
                @endif
            </div>

            <h3 class="mt-6 mb-3 text-xs font-bold uppercase tracking-wide text-muted-fg">Center occupancy</h3>
            <p x-show="loaded && centers.length === 0" x-cloak class="text-sm text-muted-fg">No evacuation centers on the map.</p>
            <div class="space-y-4">
                {{-- Same markup as <x-meter>, bound to the live feed. --}}
                <template x-for="center in centers" :key="center.id">
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-bold text-navy truncate" x-text="center.name"></span>
                            <span class="text-muted-fg tabular-nums shrink-0"
                                  x-text="center.current_occupancy + ' / ' + center.capacity + ' · ' + center.occupancy_percentage + '%'"></span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-muted overflow-hidden" role="meter" :aria-label="center.name + ' occupancy'"
                             aria-valuemin="0" :aria-valuemax="center.capacity" :aria-valuenow="center.current_occupancy">
                            <div class="h-full rounded-full transition-[width] duration-300" :class="meterFill(center.occupancy_percentage)"
                                 :style="'width:' + Math.min(100, center.occupancy_percentage) + '%'"></div>
                        </div>
                        <p class="mt-1 text-xs text-muted-fg" x-text="center.status_label + (center.accepting ? ' · accepting evacuees' : ' · not accepting')"></p>
                    </div>
                </template>
            </div>
        </x-card>
    </div>

    {{-- Stat row, computed from the same live feed --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 sm:gap-6">
        <x-stat-card icon="clipboard" label="Open requests" tone="navy"><span x-text="stats.requests">—</span></x-stat-card>
        <x-stat-card icon="siren" label="Active SOS" tone="danger"><span x-text="stats.sos">—</span></x-stat-card>
        <x-stat-card icon="users" label="Personnel on map" tone="accent"><span x-text="stats.personnel">—</span></x-stat-card>
        <x-stat-card icon="house" label="Centers accepting" tone="success">
            <span x-text="stats.centersAccepting">—</span><span class="text-sm text-muted-fg font-normal" x-text="' / ' + stats.centers"></span>
        </x-stat-card>
        <x-stat-card icon="activity" label="Evacuees sheltered" tone="warning">
            <span x-text="stats.sheltered">—</span>
            <x-slot:hint><span x-text="stats.shelterCapacity ? 'of ' + stats.shelterCapacity + ' capacity' : ''"></span></x-slot:hint>
        </x-stat-card>
    </div>
</div>

@push('scripts')
<script>
    function sagipMap() {
        // Leaflet objects stay outside Alpine's reactive state.
        let map;
        const token = (name) => getComputedStyle(document.documentElement).getPropertyValue('--color-' + name).trim();
        const colors = {
            danger: token('danger'), alert: token('alert'), warning: token('warning'),
            success: token('success'), accent: token('accent'), neutral: token('neutral'),
        };
        const urgencyColor = { critical: colors.danger, high: colors.alert, average: colors.warning, low: colors.success };
        const centerColor = { open: colors.success, standby: colors.accent, full: colors.danger, closed: colors.neutral };
        const houseGlyph = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function pin(color, size, { sos = false, glyph = '' } = {}) {
            return L.divIcon({
                className: '',
                html: `<span class="sg-pin${sos ? ' sg-pin--sos' : ''}" style="--pin:${color}">${glyph}</span>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
                popupAnchor: [0, -size / 2],
            });
        }

        const layers = {};

        return {
            centers: [],
            loaded: false,
            updatedAt: null,
            stats: { requests: '—', sos: '—', personnel: '—', centers: 0, centersAccepting: '—', sheltered: '—', shelterCapacity: 0 },

            init() {
                map = L.map('map').setView([{{ $hall['latitude'] }}, {{ $hall['longitude'] }}], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                // Hotspots sit underneath everything else so they never swallow a click on
                // an actual incident marker.
                layers.hotspots = L.layerGroup().addTo(map);
                layers.centers = L.layerGroup().addTo(map);
                layers.requests = L.layerGroup().addTo(map);
                layers.personnel = L.layerGroup().addTo(map);

                document.querySelectorAll('[id^="layer-"]').forEach(toggle => {
                    toggle.addEventListener('change', () => {
                        const layer = layers[toggle.id.replace('layer-', '')];
                        if (!layer) return;
                        toggle.checked ? map.addLayer(layer) : map.removeLayer(layer);
                    });
                });

                this.refresh();
                setInterval(() => this.refresh(), 15000);
            },

            meterFill(percent) {
                if (percent >= 100) return 'bg-danger';
                if (percent >= 80) return 'bg-alert';
                if (percent >= 50) return 'bg-warning';
                return 'bg-success';
            },

            refresh() {
                fetch('{{ route('map.data') }}')
                    .then(r => r.json())
                    .then(data => {
                        Object.values(layers).forEach(layer => layer.clearLayers());
                        const requests = data.requests ?? [];
                        const personnel = data.personnel ?? [];
                        const centers = data.evacuation_centers ?? [];

                        (data.hotspots ?? []).forEach(spot => {
                            L.circle([spot.latitude, spot.longitude], {
                                radius: 90 + (spot.intensity * 210),
                                color: colors.danger,
                                fillColor: colors.danger,
                                fillOpacity: 0.12 + (spot.intensity * 0.3),
                                opacity: 0.4 + (spot.intensity * 0.6),
                                weight: 1,
                            }).bindPopup(
                                `<strong>Incident hotspot</strong><br>${esc(spot.incidents)} incidents `
                                + `(${esc(spot.recent_incidents)} recent)<br>`
                                + `Mostly: ${esc((spot.dominant_category ?? 'unclassified').replace(/_/g, ' '))}`
                            ).addTo(layers.hotspots);
                        });

                        centers.forEach(center => {
                            L.marker([center.latitude, center.longitude], {
                                icon: pin(centerColor[center.status] || colors.neutral, 30, { glyph: houseGlyph }),
                                title: center.name,
                            }).bindPopup(
                                `<strong>${esc(center.name)}</strong><br>${esc(center.status_label)}<br>`
                                + `Occupancy: ${esc(center.current_occupancy)} / ${esc(center.capacity)} `
                                + `(${esc(center.occupancy_percentage)}%)<br>`
                                + `${center.accepting ? 'Accepting evacuees' : 'Not accepting evacuees'}`
                                + (center.contact_number ? `<br>Contact: ${esc(center.contact_number)}` : '')
                            ).addTo(layers.centers);
                        });

                        requests.forEach(req => {
                            const isSos = req.source !== 'form';
                            L.marker([req.latitude, req.longitude], {
                                icon: pin(urgencyColor[req.urgency] || colors.neutral, isSos ? 22 : 18, { sos: isSos }),
                                title: `Request #${req.id}`,
                                zIndexOffset: isSos ? 1000 : 500,
                            }).bindPopup(
                                `<strong>Request #${esc(req.id)}</strong>`
                                + (isSos ? ' <em>(SOS)</em>' : '')
                                + `<br>${esc(req.category ?? '—')} · ${esc(req.urgency ?? '—')}<br>Status: ${esc(req.status)}`
                            ).addTo(layers.requests);
                        });

                        personnel.forEach(p => {
                            L.marker([p.latitude, p.longitude], { icon: pin(colors.accent, 16), title: p.name }).bindPopup(
                                `<strong>${esc(p.name)}</strong><br>${esc(p.specialization)}<br>Workload: ${esc(p.current_workload)}`
                            ).addTo(layers.personnel);
                        });

                        const sheltered = centers.reduce((sum, c) => sum + Number(c.current_occupancy || 0), 0);
                        this.centers = centers;
                        this.stats = {
                            requests: requests.length,
                            sos: requests.filter(r => r.source !== 'form').length,
                            personnel: personnel.length,
                            centers: centers.length,
                            centersAccepting: centers.filter(c => c.accepting).length,
                            sheltered,
                            shelterCapacity: centers.reduce((sum, c) => sum + Number(c.capacity || 0), 0),
                        };
                        this.loaded = true;
                        this.updatedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    });
            },
        };
    }
</script>
@endpush
@endsection
