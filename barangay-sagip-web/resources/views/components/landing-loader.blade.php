{{--
    Landing-page loading view: the SAGIP emblem on navy, shown once per browser session before the
    landing page. It covers the page (no scroll, no focus) for 3 s, then fades out. Shares its "seen"
    flag and #sg-splash hook with <x-splash-screen>, so the app never shows two splashes in one visit. Place directly after <body>. Without JavaScript it never shows.
--}}
<style>
    .sg-splash {
        position: fixed; inset: 0; z-index: 2000;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2.25rem;
        background: radial-gradient(circle at 50% 42%, rgb(var(--c-accent) / 0.22) 0%, transparent 60%), var(--color-navy);
        transition: opacity 450ms ease;
    }
    html.sg-splash-seen .sg-splash { display: none; }
    html.sg-loading { overflow: hidden; }
    .sg-splash.is-leaving { opacity: 0; pointer-events: none; }
    .sg-splash__emblem {
        height: 10rem; width: 10rem; border-radius: 9999px; background: rgb(var(--c-white));
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 0 48px 8px rgb(var(--c-accent) / 0.45);
        animation: sg-loader-in 600ms cubic-bezier(.2, .7, .2, 1) both;
    }
    .sg-splash__emblem img { height: 78%; width: auto; }
    .sg-splash__bar { position: relative; width: 9rem; height: 3px; border-radius: 9999px; background: rgb(var(--c-white) / 0.12); overflow: hidden; }
    .sg-splash__bar::after {
        content: ''; position: absolute; inset: 0 auto 0 0; width: 40%; border-radius: inherit;
        background: var(--color-accent); animation: sg-loader-bar 1.1s ease-in-out infinite;
    }
    @keyframes sg-loader-in { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
    @keyframes sg-loader-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes sg-loader-bar { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
    @media (prefers-reduced-motion: reduce) {
        .sg-splash__emblem { animation-name: sg-loader-fade; }
        .sg-splash__bar::after { animation: none; width: 100%; opacity: 0.6; }
    }
</style>
<noscript><style>.sg-splash { display: none !important; }</style></noscript>
<script>
    try {
        document.documentElement.classList.add(sessionStorage.getItem('sagip.splashSeen') ? 'sg-splash-seen' : 'sg-loading');
    } catch (error) {
        document.documentElement.classList.add('sg-loading');
    }
</script>

<div class="sg-splash" id="sg-splash" role="status" aria-live="polite">
    <div class="sg-splash__emblem">
        <img src="{{ asset('images/barangay-sagip-logo.svg') }}" alt="" width="400" height="480">
    </div>
    <div class="sg-splash__bar" aria-hidden="true"></div>
    <span class="sr-only">Loading Barangay SAGIP…</span>
</div>

<script>
    (function () {
        const root = document.documentElement;
        const splash = document.getElementById('sg-splash');
        if (!splash || root.classList.contains('sg-splash-seen')) {
            splash?.remove();
            return;
        }

        try { sessionStorage.setItem('sagip.splashSeen', '1'); } catch (error) {}

        // Keep the page underneath out of the tab order until the loader leaves.
        const setPageInert = (inert) => {
            for (const el of document.body.children) {
                if (el !== splash && el.tagName !== 'SCRIPT') el.inert = inert;
            }
        };
        document.addEventListener('DOMContentLoaded', () => { if (!done) setPageInert(true); }, { once: true });

        let done = false;
        function dismiss() {
            if (done) return;
            done = true;
            setPageInert(false);
            root.classList.remove('sg-loading');
            splash.classList.add('is-leaving');
            splash.addEventListener('transitionend', () => splash.remove(), { once: true });
            setTimeout(() => splash.remove(), 650); // in case transitions are disabled
        }

        setTimeout(dismiss, 3000); // fixed display time, whether or not the page has finished loading
    })();
</script>
