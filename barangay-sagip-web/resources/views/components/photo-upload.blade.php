@props(['user', 'action', 'label' => 'Profile Photo'])
@php($size = \App\Http\Requests\UpdateProfilePhotoRequest::CAPTURE_SIZE)

{{--
    Camera-only face capture, modelled on e-wallet selfie verification: the photo can only come
    from a live camera frame in which exactly one face is centred, facing forward, well lit, and
    has blinked (a printed photo or a screen held up to the camera cannot blink). Once verified,
    the photo is saved automatically.
--}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      x-data="faceCapture({ size: {{ $size }} })"
      {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @csrf
    @method('PUT')

    <p class="block text-sm font-medium">{{ $label }}</p>

    <input type="file" name="photo" x-ref="photo" class="hidden" tabindex="-1" aria-hidden="true">

    <div class="flex items-center gap-4" x-show="state === 'idle' || state === 'error'">
        <x-avatar :user="$user" size="h-20 w-20 text-xl" />
        <div class="min-w-0 space-y-1">
            <button type="button" @click="start()"
                    class="bg-navy text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-accent transition">
                {{ $user->profile_photo_path ? 'Retake Face Photo' : 'Take Face Photo' }}
            </button>
            <p class="text-xs text-gray-500">Uses your camera. Uploading saved pictures is not allowed.</p>
        </div>
    </div>

    <div x-show="['loading', 'scanning'].includes(state)" x-cloak class="space-y-3">
        <div class="relative mx-auto aspect-[3/4] w-full max-w-xs overflow-hidden rounded-2xl bg-black">
            <video x-ref="video" playsinline muted class="h-full w-full -scale-x-100 object-cover"></video>
            {{-- Dimmed frame with an oval cut-out, like the guide on e-wallet selfie screens. --}}
            <div class="pointer-events-none absolute left-1/2 top-[45%] h-[62%] w-[72%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-4 shadow-[0_0_0_9999px_rgba(0,0,0,0.55)] transition-colors duration-200"
                 :class="ready ? 'border-green-400' : (faceFound ? 'border-amber-300' : 'border-white/80')"></div>

            <div class="absolute inset-x-0 top-3 flex justify-center" x-show="state === 'scanning'">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-white shadow"
                      :class="ready ? 'bg-green-600' : (faceFound ? 'bg-amber-500' : 'bg-gray-700/90')">
                    <svg x-show="faceFound" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    <span x-text="faceFound ? 'Face recognized' : 'No face detected'"></span>
                </span>
            </div>

            <p class="absolute inset-x-0 bottom-0 bg-black/60 px-3 py-2 text-center text-sm font-medium text-white" x-text="instruction" aria-live="polite"></p>
        </div>

        <ol class="mx-auto max-w-xs space-y-1 text-sm" x-show="state === 'scanning'">
            <template x-for="(item, index) in checklist" :key="index">
                <li class="flex items-center gap-2" :class="item.done ? 'text-green-700 font-medium' : 'text-gray-500'">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                          :class="item.done ? 'bg-green-600 text-white' : 'border border-gray-300'"
                          x-text="item.done ? '✓' : index + 1"></span>
                    <span x-text="item.label"></span>
                </li>
            </template>
        </ol>

        <div class="flex justify-center">
            <button type="button" @click="cancel()" class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-gray-50">Cancel</button>
        </div>
    </div>

    <div x-show="state === 'saving'" x-cloak class="flex items-center gap-4" role="status">
        <img :src="preview" alt="Captured face photo" class="h-20 w-20 shrink-0 rounded-full object-cover ring-4 ring-green-500">
        <div class="space-y-1">
            <p class="flex items-center gap-1.5 text-sm font-bold text-green-700">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                Face verified
            </p>
            <p class="text-sm text-gray-500">Saving your photo…</p>
        </div>
    </div>

    <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600"></p>
    @error('photo')
        <p x-show="!error" class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</form>

@once
<script>
    window.faceCapture = ({ size }) => {
        // Kept outside Alpine's reactive state: Alpine wraps stored objects in a Proxy, which the
        // video element and MediaPipe reject (e.g. srcObject only accepts a real MediaStream).
        let stream = null;
        let landmarker = null;
        let frameRequest = null;
        let probeCanvas = null;
        let lastFrameTime = -1;
        let openEyeBaseline = 1;

        return {
            state: 'idle',
            instruction: '',
            error: '',
            preview: null,
            faceFound: false,
            ready: false,
            step: 'position',
            eyesClosedSeen: false,
            steadySince: null,

            get checklist() {
                return [
                    { label: 'Face recognized inside the oval', done: this.ready },
                    { label: 'Blink once', done: this.step === 'hold' },
                    { label: 'Hold still — photo saves automatically', done: false },
                ];
            },

            async start() {
                this.reset();
                this.state = 'loading';
                this.instruction = 'Starting camera…';

                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    return this.fail('Your browser cannot open the camera on this page. Open the site over HTTPS in an up-to-date browser.');
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
                        audio: false,
                    });
                } catch (e) {
                    return this.fail(e.name === 'NotAllowedError'
                        ? 'Camera access was blocked. Allow camera access for this site, then try again.'
                        : 'No camera could be opened on this device.');
                }

                if (this.state !== 'loading') {
                    return this.stop();
                }

                try {
                    const video = this.$refs.video;
                    video.srcObject = stream;
                    await video.play();

                    this.instruction = 'Loading face check…';
                    landmarker = await window.faceCapture.loadLandmarker();
                } catch (e) {
                    console.error(e);
                    return this.fail('The face check could not start. Check your internet connection and try again.');
                }

                if (this.state !== 'loading') {
                    return this.stop();
                }
                this.state = 'scanning';
                this.instruction = 'Place your face inside the oval';
                this.scan();
            },

            scan() {
                if (this.state !== 'scanning') {
                    return;
                }
                const video = this.$refs.video;
                try {
                    if (video.readyState >= 2 && video.currentTime !== lastFrameTime) {
                        lastFrameTime = video.currentTime;
                        this.evaluate(landmarker.detectForVideo(video, performance.now()), video);
                    }
                } catch (e) {
                    console.error(e);
                    return this.fail('The face check stopped unexpectedly. Please try again.');
                }
                frameRequest = requestAnimationFrame(() => this.scan());
            },

            /**
             * Moves through the liveness steps: position the face, blink once, then hold still
             * with eyes open until the frame is captured and saved automatically.
             */
            evaluate(result, video) {
                const faces = result.faceLandmarks ?? [];
                this.faceFound = faces.length === 1;
                const problem = this.positionProblem(faces, video);
                this.ready = !problem;

                if (problem) {
                    this.instruction = problem;
                    this.steadySince = null;
                    this.step = 'position';
                    this.eyesClosedSeen = false;
                    return;
                }

                const shapes = Object.fromEntries((result.faceBlendshapes?.[0]?.categories ?? []).map(c => [c.categoryName, c.score]));
                const blink = ((shapes.eyeBlinkLeft ?? 0) + (shapes.eyeBlinkRight ?? 0)) / 2;

                if (this.step === 'position') {
                    this.step = 'blink';
                    openEyeBaseline = blink;
                }

                // Measured against this person's own open-eye score: glasses, lighting and eye shape
                // can keep the score well above zero even with the eyes wide open.
                openEyeBaseline = Math.min(openEyeBaseline, blink);
                const eyesClosed = blink > Math.max(openEyeBaseline + 0.1, 0.35);
                const eyesOpen = blink < openEyeBaseline + 0.08;

                if (this.step === 'blink') {
                    this.instruction = 'Face recognized — now blink your eyes';
                    if (eyesClosed) {
                        this.eyesClosedSeen = true;
                    } else if (this.eyesClosedSeen && eyesOpen) {
                        this.step = 'hold';
                    }
                    return;
                }

                this.instruction = 'Hold still…';
                if (!eyesOpen) {
                    this.steadySince = null;
                    return;
                }
                this.steadySince ??= performance.now();
                if (performance.now() - this.steadySince > 500) {
                    this.capture(faces[0], video);
                }
            },

            /** Returns a message describing what is wrong with the face position, or null when it is acceptable. */
            positionProblem(faces, video) {
                if (faces.length === 0) {
                    return 'Place your face inside the oval';
                }
                if (faces.length > 1) {
                    return 'Only one person should be in the frame';
                }

                // Landmarks are normalised separately per axis, so convert to pixels before comparing distances.
                const width = video.videoWidth, height = video.videoHeight;
                const points = faces[0].map(p => ({ x: p.x * width, y: p.y * height }));
                const xs = points.map(p => p.x);
                const ys = points.map(p => p.y);
                const faceWidth = Math.max(...xs) - Math.min(...xs);
                const centerX = (Math.max(...xs) + Math.min(...xs)) / 2 / width;
                const centerY = (Math.max(...ys) + Math.min(...ys)) / 2 / height;
                // The preview is cropped to 3:4, so measure against the visible width rather than the full frame.
                const visibleWidth = Math.min(width, height * 0.75);
                const share = faceWidth / visibleWidth;

                if (share < 0.3) {
                    return 'Face recognized — move closer';
                }
                if (share > 0.9) {
                    return 'Face recognized — move a little farther away';
                }
                if (Math.abs(centerX - 0.5) > 0.12 || Math.abs(centerY - 0.45) > 0.15) {
                    return 'Face recognized — center it inside the oval';
                }

                // Nose tip (1) should sit roughly midway between the outer eye corners (33, 263) and below them.
                const nose = points[1], eyeA = points[33], eyeB = points[263];
                const eyeSpan = Math.hypot(eyeB.x - eyeA.x, eyeB.y - eyeA.y);
                const yaw = (nose.x - eyeA.x) / (eyeB.x - eyeA.x);
                const pitch = (nose.y - (eyeA.y + eyeB.y) / 2) / eyeSpan;
                if (yaw < 0.3 || yaw > 0.7 || pitch < 0.15 || pitch > 0.9) {
                    return 'Face recognized — look straight at the camera';
                }

                if (this.brightness(video) < 40) {
                    return 'Too dark — move to a brighter place';
                }

                return null;
            },

            brightness(video) {
                probeCanvas ??= Object.assign(document.createElement('canvas'), { width: 32, height: 32 });
                const context = probeCanvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(video, 0, 0, 32, 32);
                const data = context.getImageData(0, 0, 32, 32).data;
                let total = 0;
                for (let i = 0; i < data.length; i += 4) {
                    total += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                }
                return total / (data.length / 4);
            },

            /** Crops a square around the face from the live frame and submits it. */
            capture(landmarks, video) {
                this.state = 'saving';
                cancelAnimationFrame(frameRequest);

                const xs = landmarks.map(p => p.x * video.videoWidth);
                const ys = landmarks.map(p => p.y * video.videoHeight);
                const faceSize = Math.max(Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys));
                const side = Math.min(faceSize * 1.8, video.videoWidth, video.videoHeight);
                const left = Math.min(Math.max((Math.max(...xs) + Math.min(...xs)) / 2 - side / 2, 0), video.videoWidth - side);
                const top = Math.min(Math.max((Math.max(...ys) + Math.min(...ys)) / 2 - side / 2, 0), video.videoHeight - side);

                const canvas = Object.assign(document.createElement('canvas'), { width: size, height: size });
                canvas.getContext('2d').drawImage(video, left, top, side, side, 0, 0, size, size);
                this.stop();

                canvas.toBlob(blob => {
                    if (!blob) {
                        return this.fail('The photo could not be captured. Please try again.');
                    }
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([blob], 'face.jpg', { type: 'image/jpeg' }));
                    this.$refs.photo.files = transfer.files;
                    this.preview = URL.createObjectURL(blob);
                    this.$root.submit();
                }, 'image/jpeg', 0.9);
            },

            cancel() {
                this.stop();
                this.reset();
            },

            fail(message) {
                this.stop();
                this.state = 'error';
                this.error = message;
            },

            reset() {
                this.state = 'idle';
                this.error = '';
                this.faceFound = false;
                this.ready = false;
                this.step = 'position';
                this.eyesClosedSeen = false;
                this.steadySince = null;
                lastFrameTime = -1;
                this.$refs.photo.value = '';
                if (this.preview) {
                    URL.revokeObjectURL(this.preview);
                    this.preview = null;
                }
            },

            stop() {
                cancelAnimationFrame(frameRequest);
                stream?.getTracks().forEach(track => track.stop());
                stream = null;
            },
        };
    };

    /** Loads MediaPipe's face landmarker once per page and shares it between capture forms. */
    window.faceCapture.loadLandmarker = function () {
        const base = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1';
        return this.landmarker ??= import(`${base}/vision_bundle.mjs`).then(async ({ FaceLandmarker, FilesetResolver }) =>
            FaceLandmarker.createFromOptions(await FilesetResolver.forVisionTasks(`${base}/wasm`), {
                baseOptions: {
                    modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                    delegate: 'GPU',
                },
                runningMode: 'VIDEO',
                numFaces: 2,
                outputFaceBlendshapes: true,
            })
        ).catch(error => {
            this.landmarker = null;
            throw error;
        });
    };
</script>
@endonce
