{{--
    Branded splash shown once per browser session, on whichever page the app is first opened.
    It wraps real load time: it leaves on window "load", or after 2 s at most — never later.
    Place directly after <body>. Without JavaScript it never shows.
--}}
<style>
    .sg-splash {
        position: fixed; inset: 0; z-index: 2000;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2rem;
        background: radial-gradient(circle at 50% 40%, rgb(var(--c-secondary) / 0.6) 0%, transparent 65%), var(--color-navy);
        transition: opacity 400ms ease;
    }
    .sg-splash[hidden], html.sg-splash-seen .sg-splash { display: none; }
    .sg-splash.is-leaving { opacity: 0; pointer-events: none; }
    .sg-splash__logo { height: 7rem; width: auto; animation: sg-splash-in 600ms ease-out both; }
    .sg-splash__bar { position: relative; width: 10rem; height: 4px; border-radius: 9999px; background: rgb(var(--c-white) / 0.12); overflow: hidden; }
    .sg-splash__bar::after {
        content: ''; position: absolute; inset: 0 auto 0 0; width: 40%; border-radius: inherit;
        background: var(--color-accent); animation: sg-splash-bar 1.1s ease-in-out infinite;
    }
    @keyframes sg-splash-in { from { opacity: 0; transform: scale(0.94); } to { opacity: 1; transform: scale(1); } }
    @keyframes sg-splash-bar { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
    @media (prefers-reduced-motion: reduce) {
        .sg-splash__logo { animation: none; }
        .sg-splash__bar::after { animation: none; width: 100%; opacity: 0.6; }
    }
</style>
<noscript><style>.sg-splash { display: none !important; }</style></noscript>
<script>
    try {
        if (sessionStorage.getItem('sagip.splashSeen')) {
            document.documentElement.classList.add('sg-splash-seen');
        }
    } catch (error) {}
</script>

<div class="sg-splash" id="sg-splash" role="status" aria-live="polite">
    <x-brand-logo class="sg-splash__logo" />
    <div class="sg-splash__bar" aria-hidden="true"></div>
    <span class="sr-only">Loading Barangay SAGIP…</span>
</div>

<script>
    (function () {
        const splash = document.getElementById('sg-splash');
        if (!splash || document.documentElement.classList.contains('sg-splash-seen')) {
            splash?.remove();
            return;
        }

        try { sessionStorage.setItem('sagip.splashSeen', '1'); } catch (error) {}

        let done = false;
        function dismiss() {
            if (done) return;
            done = true;
            splash.classList.add('is-leaving');
            splash.addEventListener('transitionend', () => splash.remove(), { once: true });
            setTimeout(() => splash.remove(), 600); // in case transitions are disabled
        }

        if (document.readyState === 'complete') {
            dismiss();
        } else {
            window.addEventListener('load', dismiss, { once: true });
            setTimeout(dismiss, 2000); // hard cap
        }
    })();
</script>
