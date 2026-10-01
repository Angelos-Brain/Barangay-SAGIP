{{--
    Feature 9: universal back navigation.

    Rendered once from layouts/app.blade.php, so every authenticated screen
    carries it. The href is resolved server-side by App\Support\BackNavigator to
    a route the current role may actually open; the click handler prefers the
    browser's own history when the entry came from inside this application,
    which keeps scroll position and form state on the page behind.
--}}
@php
    $navigator = app(\App\Support\BackNavigator::class);
    $user = auth()->user();
    $currentUrl = url()->current();
@endphp

@if ($navigator->shouldShow($user, $currentUrl))
    @php
        $backUrl = $navigator->resolve($user, $currentUrl, url()->previous());
        $label = $navigator->labelFor($user, $backUrl);
    @endphp
    <div class="mb-4">
        <a href="{{ $backUrl }}"
           data-back-link
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-accent transition"
           aria-label="{{ $label }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ $label }}
        </a>
    </div>

    @once
        @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const link = event.target.closest('[data-back-link]');
                if (!link) return;

                // Only take over when this page was reached from inside the app
                // and the history entry actually points at the same place the
                // server resolved. Otherwise let the plain href do its job.
                const referrer = document.referrer;
                if (!referrer) return;

                try {
                    const from = new URL(referrer);
                    const target = new URL(link.href, window.location.origin);

                    if (from.origin !== window.location.origin) return;
                    if (from.pathname !== target.pathname) return;

                    event.preventDefault();
                    window.history.back();
                } catch (error) {
                    // Malformed URL — fall through to the href.
                }
            });
        </script>
        @endpush
    @endonce
@endif
