{{--
    Feature 2: the persistent SOS control, rendered on every authenticated page
    from layouts/app.blade.php.

    Anti-false-alarm flow (nothing reaches the server until the last step):
      1. press and hold the button for sagip.sos.hold_milliseconds — GPS is
         read in the background from the moment the press starts;
      2. pick a reason ("Other" needs a short description);
      3. "Confirm SOS: <reason> at <location>?" counts down
         sagip.sos.cancel_window_seconds, then dispatches on its own. Cancel
         returns to idle with no record created; "Send now" skips the wait.

    The online attempt is aborted after sagip.sos.timeout_seconds. On timeout,
    network failure, or a non-2xx reply the component falls back twice over:
    it asks the server to send the SMS through the gateway (which works when the
    API was merely slow), and it offers a pre-filled `sms:` link so the resident
    can send the same payload from their own phone with no data connection at
    all. A 429 is the cooldown — an SOS is already active — and is NOT a
    failure, so it never triggers the SMS fallback.
--}}
@php
    $timeoutSeconds = (int) config('sagip.sos.timeout_seconds', 8);
    $hotline = (string) config('sagip.sos.hotline_number');
@endphp
<div x-data="sagipSos({
        storeUrl: '{{ route('sos.store') }}',
        fallbackUrl: '{{ route('sos.smsFallback') }}',
        timeoutMs: {{ $timeoutSeconds * 1000 }},
        holdMs: {{ (int) config('sagip.sos.hold_milliseconds', 2000) }},
        cancelWindowSeconds: {{ (int) config('sagip.sos.cancel_window_seconds', 5) }},
        otherMaxLength: {{ \App\Enums\SosReason::OTHER_MAX_LENGTH }},
        reasons: @js(\App\Enums\SosReason::options()),
        hotline: @js($hotline),
        residentName: @js(auth()->user()->name),
     })"
     @keydown.escape.window="if (state === 'reason' || state === 'confirm') cancel()"
     class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[1200] flex flex-col items-end gap-3">
    {{-- Steps 2–3 open as a focused dialog over the page (MASTER.md → Modals). --}}
    <div x-show="state === 'reason' || state === 'confirm'" x-cloak x-transition.opacity
         class="fixed inset-0 z-[1250] bg-navy/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
         @click.self="cancel()">

        {{-- Step 2: why --}}
        <div x-show="state === 'reason'"
             class="w-full max-w-md rounded-2xl bg-surface shadow-pop p-6 sm:p-8"
             role="dialog" aria-modal="true" aria-labelledby="sos-reason-heading">
            <div class="flex items-center gap-3">
                <span class="h-12 w-12 rounded-2xl bg-danger/10 text-danger inline-flex items-center justify-center shrink-0">
                    <x-icon name="siren" class="w-6 h-6" />
                </span>
                <div>
                    <p id="sos-reason-heading" class="text-xl font-bold text-navy">What is the emergency?</p>
                    <p class="text-sm text-muted-fg">Your GPS location is being attached.</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <template x-for="(label, value) in config.reasons" :key="value">
                    <button type="button" @click="chooseReason(value)"
                            class="min-h-14 rounded-xl border-2 px-3 py-3 text-sm font-bold text-left transition-colors duration-200 cursor-pointer"
                            :class="reason === value
                                ? 'border-danger bg-danger text-white'
                                : 'border-line text-navy hover:border-danger hover:bg-danger/5'"
                            :aria-pressed="reason === value"
                            x-text="label"></button>
                </template>
            </div>

            <div x-show="reason === 'other'" x-cloak class="mt-4">
                <label for="sos-reason-other" class="block text-sm font-bold text-navy">Describe it in a few words</label>
                <input id="sos-reason-other" type="text" x-model="reasonOther" x-ref="reasonOther"
                       :maxlength="config.otherMaxLength"
                       @keydown.enter.prevent="submitOther()"
                       class="mt-1.5 w-full rounded-xl border border-line px-4 py-3 text-base transition-colors duration-200 focus:border-danger focus:outline-none focus:ring-[3px] focus:ring-danger/15">
                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="text-xs text-muted-fg tabular-nums"
                          x-text="reasonOther.length + '/' + config.otherMaxLength"></span>
                    <button type="button" @click="submitOther()" :disabled="reasonOther.trim() === ''"
                            class="min-h-11 rounded-xl bg-danger px-5 text-sm font-bold text-white transition-opacity duration-200 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed">
                        Continue
                    </button>
                </div>
            </div>

            <button type="button" @click="cancel()"
                    class="mt-6 w-full min-h-11 rounded-xl border border-line text-sm font-bold text-muted-fg hover:bg-canvas transition-colors duration-200 cursor-pointer">
                Cancel
            </button>
        </div>

        {{-- Step 3: confirm with a cancel window --}}
        <div x-show="state === 'confirm'"
             class="w-full max-w-md rounded-2xl bg-surface shadow-pop p-6 sm:p-8 text-center"
             role="alertdialog" aria-modal="true" aria-labelledby="sos-confirm-heading" aria-describedby="sos-confirm-countdown">
            <div class="mx-auto h-24 w-24 rounded-full bg-danger/10 ring-8 ring-danger/5 flex flex-col items-center justify-center">
                <span class="text-4xl font-bold text-danger tabular-nums leading-none" x-text="countdown"></span>
                <span class="text-[11px] font-bold uppercase tracking-wide text-danger mt-1">seconds</span>
            </div>
            <p id="sos-confirm-heading" class="mt-5 text-lg font-bold text-navy">
                Confirm SOS: <span x-text="reasonLabel"></span> at <span x-text="locationLabel"></span>?
            </p>
            <p id="sos-confirm-countdown" class="mt-1 text-sm text-muted-fg" aria-live="polite">
                Sending in <span class="font-bold" x-text="countdown"></span>s…
            </p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" @click="cancel()" x-ref="cancelButton"
                        class="min-h-12 rounded-xl border-2 border-line bg-surface text-sm font-bold text-navy hover:bg-canvas transition-colors duration-200 cursor-pointer">
                    Cancel
                </button>
                <button type="button" @click="dispatch()"
                        class="min-h-12 rounded-xl bg-danger text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200 cursor-pointer">
                    Send now
                </button>
            </div>
        </div>
    </div>

    {{-- Status panel --}}
    <div x-show="['sending', 'sent', 'fallback', 'failed', 'cooldown', 'hint'].includes(state)" x-cloak
         class="w-80 max-w-[calc(100vw-2rem)] rounded-2xl shadow-pop border px-5 py-4 text-sm bg-surface"
         :class="{
            'border-line text-navy': state === 'sending' || state === 'hint',
            'border-success/30 text-success': state === 'sent',
            'border-accent/30 text-accent': state === 'cooldown',
            'border-warning/30 text-warning': state === 'fallback',
            'border-danger/30 text-danger': state === 'failed',
         }"
         role="status" aria-live="assertive">
        <p class="font-bold text-base" x-text="headline"></p>
        <p class="text-sm mt-1 text-muted-fg" x-text="detail"></p>

        {{-- Optional corroboration: never required, never on the dispatch path. --}}
        <div x-show="state === 'sent' && attachmentUrl && attachmentState !== 'done'" x-cloak class="mt-3">
            <label class="block text-xs font-bold text-navy">
                Optional: add a photo or voice note
                <input type="file" accept="image/*,audio/*" @change="uploadAttachment($event)"
                       :disabled="attachmentState === 'uploading'"
                       class="mt-1.5 block w-full text-xs text-muted-fg file:mr-2 file:rounded-lg file:border-0 file:bg-success file:px-3 file:py-1.5 file:font-bold file:text-white file:cursor-pointer">
            </label>
            <p x-show="attachmentState === 'uploading'" class="text-xs mt-1 text-muted-fg">Uploading…</p>
            <p x-show="attachmentState === 'error'" class="text-xs mt-1 text-danger" x-text="attachmentError"></p>
        </div>
        <p x-show="attachmentState === 'done'" x-cloak class="text-xs mt-1">Attachment sent to responders.</p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            {{-- Offline fallback: hand the payload to the phone's own SMS app. --}}
            <a x-show="state === 'fallback' || state === 'failed'" x-cloak
               :href="smsHref"
               class="inline-flex items-center gap-1.5 min-h-10 rounded-xl bg-navy px-3 text-xs font-bold text-white hover:bg-accent transition-colors duration-200">
                <x-icon name="phone" class="w-4 h-4" /> Text the hotline instead
            </a>

            <a x-show="requestUrl" x-cloak :href="requestUrl"
               class="inline-flex items-center min-h-10 px-2 text-xs font-bold text-accent hover:underline">
                View my SOS
            </a>

            <button type="button" @click="reset()"
                    class="ml-auto min-h-10 px-2 text-xs font-bold text-muted-fg hover:underline cursor-pointer">Dismiss</button>
        </div>
    </div>

    {{-- Step 1: the button itself — press and hold --}}
    <button type="button"
            @pointerdown.prevent="startHold()"
            @pointerup="endHold()" @pointerleave="endHold()" @pointercancel="endHold()"
            @keydown.space.prevent="if (!$event.repeat) startHold()"
            @keydown.enter.prevent="if (!$event.repeat) startHold()"
            @keyup.space="endHold()" @keyup.enter="endHold()"
            @contextmenu.prevent
            :disabled="state === 'sending'"
            class="relative h-20 w-20 rounded-full bg-danger text-white font-bold shadow-pop ring-[6px] ring-danger/20
                   hover:ring-danger/30 transition-[box-shadow,transform] duration-200 disabled:opacity-60 disabled:cursor-wait select-none touch-none cursor-pointer
                   flex flex-col items-center justify-center leading-none"
            :class="holding ? 'scale-95' : ''"
            aria-label="Send an emergency SOS with my location"
            aria-describedby="sos-hold-hint">
        {{-- Hold progress ring --}}
        <svg class="absolute inset-0 -rotate-90 pointer-events-none" viewBox="0 0 64 64" aria-hidden="true">
            <circle cx="32" cy="32" r="30" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"
                    stroke-dasharray="188.5" :stroke-dashoffset="188.5 * (1 - holdProgress)"
                    :class="holding ? 'opacity-100' : 'opacity-0'"></circle>
        </svg>
        <x-icon name="siren" class="w-6 h-6 mb-1" />
        <span class="text-sm tracking-wide" x-text="state === 'sending' ? '...' : (holding ? 'HOLD' : 'SOS')"></span>
        <span id="sos-hold-hint" class="sr-only">Press and hold to start an SOS.</span>
    </button>
</div>

@once
    @push('scripts')
    <script>
        function sagipSos(config) {
            return {
                config,
                state: 'idle',
                headline: '',
                detail: '',
                requestUrl: null,
                smsBody: '',

                holding: false,
                holdProgress: 0,
                holdStartedAt: null,
                holdFrame: null,

                reason: null,
                reasonOther: '',
                countdown: 0,
                countdownTimer: null,

                positionPromise: null,
                position: null,

                attachmentUrl: null,
                attachmentState: 'idle',
                attachmentError: '',

                get smsHref() {
                    return 'sms:' + config.hotline + '?&body=' + encodeURIComponent(this.smsBody);
                },

                get reasonLabel() {
                    if (this.reason === 'other') {
                        return 'Other — ' + this.reasonOther.trim();
                    }

                    return config.reasons[this.reason] ?? '';
                },

                get locationLabel() {
                    if (!this.position) {
                        return 'your current location (locating…)';
                    }

                    const coords = this.position.coords;
                    const accuracy = coords.accuracy ? ' (±' + Math.round(coords.accuracy) + ' m)' : '';

                    return coords.latitude.toFixed(5) + ', ' + coords.longitude.toFixed(5) + accuracy;
                },

                reset() {
                    this.stopCountdown();
                    this.state = 'idle';
                    this.requestUrl = null;
                    this.reason = null;
                    this.reasonOther = '';
                    this.attachmentUrl = null;
                    this.attachmentState = 'idle';
                    this.attachmentError = '';
                },

                /** Cancel before dispatch: nothing has been sent, so nothing is filed. */
                cancel() {
                    this.reset();
                },

                startHold() {
                    if (this.state !== 'idle' && this.state !== 'hint') {
                        return;
                    }

                    this.state = 'idle';
                    this.holding = true;
                    this.holdProgress = 0;
                    this.holdStartedAt = performance.now();

                    // Start the GPS fix now so the hold time is not wasted.
                    this.beginLocating();

                    const tick = (now) => {
                        if (!this.holding) {
                            return;
                        }

                        this.holdProgress = Math.min(1, (now - this.holdStartedAt) / config.holdMs);

                        if (this.holdProgress >= 1) {
                            this.holding = false;
                            this.holdProgress = 0;
                            this.state = 'reason';
                            return;
                        }

                        this.holdFrame = requestAnimationFrame(tick);
                    };

                    this.holdFrame = requestAnimationFrame(tick);
                },

                endHold() {
                    if (!this.holding) {
                        return;
                    }

                    cancelAnimationFrame(this.holdFrame);
                    this.holding = false;
                    this.holdProgress = 0;
                    this.state = 'hint';
                    this.headline = 'Keep holding to send an SOS';
                    this.detail = 'Press and hold the SOS button for ' + (config.holdMs / 1000) + ' seconds.';
                },

                chooseReason(value) {
                    this.reason = value;

                    if (value === 'other') {
                        this.$nextTick(() => this.$refs.reasonOther.focus());
                        return;
                    }

                    this.reasonOther = '';
                    this.startCountdown();
                },

                submitOther() {
                    if (this.reasonOther.trim() === '') {
                        return;
                    }

                    this.startCountdown();
                },

                startCountdown() {
                    this.state = 'confirm';
                    this.countdown = config.cancelWindowSeconds;
                    this.$nextTick(() => this.$refs.cancelButton.focus());

                    this.countdownTimer = setInterval(() => {
                        this.countdown -= 1;

                        if (this.countdown <= 0) {
                            this.dispatch();
                        }
                    }, 1000);
                },

                stopCountdown() {
                    clearInterval(this.countdownTimer);
                    this.countdownTimer = null;
                },

                deviceId() {
                    const key = 'sagip.deviceId';

                    try {
                        let id = localStorage.getItem(key);

                        if (!id) {
                            id = (crypto.randomUUID?.() ?? (Date.now().toString(36) + Math.random().toString(36).slice(2)))
                                .replace(/[^A-Za-z0-9_-]/g, '');
                            localStorage.setItem(key, id);
                        }

                        return id;
                    } catch (error) {
                        return null;
                    }
                },

                beginLocating() {
                    this.position = null;
                    this.positionPromise = this.readPosition().then((position) => {
                        this.position = position;
                        return position;
                    });
                    // Surface the failure only when dispatch actually awaits it.
                    this.positionPromise.catch(() => {});
                },

                /** Step 4: the only place anything is sent to the server. */
                async dispatch() {
                    if (this.state !== 'confirm') {
                        return;
                    }

                    this.stopCountdown();
                    this.state = 'sending';
                    this.headline = 'Reading your location...';
                    this.detail = 'Keep this page open.';
                    this.requestUrl = null;

                    const reasonText = this.reasonLabel;
                    let position;

                    try {
                        // The fix started during the hold; if it timed out while
                        // the resident was choosing a reason, try once more.
                        position = await (this.positionPromise ?? Promise.reject())
                            .catch(() => this.readPosition());
                    } catch (error) {
                        this.state = 'failed';
                        this.headline = 'Location unavailable';
                        this.detail = 'Turn on GPS and location permission, then press SOS again.';
                        this.smsBody = 'SAGIP SOS: ' + config.residentName + ' needs help: ' + reasonText + '. Location unavailable.';
                        return;
                    }

                    const payload = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy ?? null,
                        triggered_at: new Date().toISOString(),
                        reason: this.reason,
                        reason_other: this.reason === 'other' ? this.reasonOther.trim() : null,
                        device_id: this.deviceId(),
                    };

                    this.smsBody = 'SAGIP SOS: ' + config.residentName + ' needs help: ' + reasonText + '. Location '
                        + payload.latitude.toFixed(6) + ',' + payload.longitude.toFixed(6)
                        + ' at ' + new Date().toLocaleString();

                    this.headline = 'Sending your SOS...';
                    this.detail = 'Waiting for the barangay to acknowledge.';

                    let response;
                    let data;

                    try {
                        response = await this.post(config.storeUrl, payload, config.timeoutMs);
                        data = await response.json();
                    } catch (error) {
                        await this.fallbackToSms(payload);
                        return;
                    }

                    if (response.status === 429 && data.cooldown) {
                        this.state = 'cooldown';
                        this.headline = 'Your SOS is already active';
                        this.detail = data.message;
                        this.requestUrl = data.redirect_url ?? null;
                        return;
                    }

                    if (!response.ok) {
                        await this.fallbackToSms(payload);
                        return;
                    }

                    this.state = 'sent';
                    this.headline = 'SOS sent';
                    this.detail = data.message;
                    this.requestUrl = data.redirect_url ?? null;
                    this.attachmentUrl = data.attachment_url ?? null;
                },

                /**
                 * The online path did not answer in time. Ask the server to send
                 * the SMS through the gateway; if even that cannot be reached,
                 * the resident still has the `sms:` link.
                 */
                async fallbackToSms(payload) {
                    this.state = 'fallback';
                    this.headline = 'No answer from the barangay';
                    this.detail = 'Falling back to SMS...';

                    try {
                        const response = await this.post(config.fallbackUrl, payload, config.timeoutMs);
                        const data = await response.json();

                        if (response.ok && data.ok) {
                            this.state = 'fallback';
                            this.headline = 'SOS sent by SMS';
                            this.detail = data.message;
                            this.requestUrl = null;
                            return;
                        }

                        this.detail = data.message || 'The SMS gateway did not accept the message.';
                    } catch (error) {
                        this.state = 'failed';
                        this.headline = 'You appear to be offline';
                        this.detail = 'Tap below to text the barangay hotline from your phone.';
                    }
                },

                async uploadAttachment(event) {
                    const file = event.target.files[0];

                    if (!file || !this.attachmentUrl) {
                        return;
                    }

                    this.attachmentState = 'uploading';
                    const body = new FormData();
                    body.append('attachment', file);

                    try {
                        const response = await fetch(this.attachmentUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body,
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.errors?.attachment?.[0] ?? data.message ?? 'Upload failed.');
                        }

                        this.attachmentState = 'done';
                    } catch (error) {
                        this.attachmentState = 'error';
                        this.attachmentError = error.message;
                    }
                },

                readPosition() {
                    return new Promise((resolve, reject) => {
                        if (!navigator.geolocation) {
                            reject(new Error('unsupported'));
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: config.timeoutMs,
                            maximumAge: 0,
                        });
                    });
                },

                post(url, payload, timeoutMs) {
                    const controller = new AbortController();
                    const timer = setTimeout(() => controller.abort(), timeoutMs);

                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(payload),
                        signal: controller.signal,
                    }).finally(() => clearTimeout(timer));
                },
            };
        }
    </script>
    @endpush
@endonce
