<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay SAGIP — Emergency Response for Calatagan Tibang</title>
    <meta name="description" content="Barangay SAGIP is the emergency reporting and response system of Barangay Calatagan Tibang, Virac, Catanduanes.">
    <x-design-tokens />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>document.documentElement.classList.add('js');</script>
    {{-- Motion spec: design-system/barangaysafe/pages/landing.md --}}
    <style>
        section[id] { scroll-margin-top: 5rem; }
        :root { --ease-out: cubic-bezier(.2, .7, .2, 1); }

        /* Hero entrance — waits for the splash (html.hero-go) */
        html.js .hero-in { opacity: 0; transform: translateY(16px); }
        html.hero-go .hero-in { animation: hero-in 600ms var(--ease-out) both; animation-delay: calc(var(--i, 0) * 120ms); }
        @keyframes hero-in { to { opacity: 1; transform: none; } }

        /* Hero background: radar pulse + drifting glow */
        .radar-ring {
            position: absolute; inset: 0; border-radius: 9999px; border: 1px solid rgb(var(--c-accent) / 0.55);
            transform: scale(0.15); opacity: 0; animation: radar 4.8s ease-out infinite;
        }
        .radar-ring:nth-child(2) { animation-delay: 1.6s; }
        .radar-ring:nth-child(3) { animation-delay: 3.2s; }
        @keyframes radar { 0% { transform: scale(0.15); opacity: 0; } 15% { opacity: 0.9; } 100% { transform: scale(1); opacity: 0; } }
        .hero-glow { animation: glow-drift 20s ease-in-out infinite alternate; }
        @keyframes glow-drift { from { transform: translate(0, 0); } to { transform: translate(-8%, 6%); } }

        /* Scroll cue */
        .scroll-cue { animation: cue 1.6s ease-in-out infinite; }
        @keyframes cue { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(6px); } }

        /* Scroll reveal (once) */
        html.js .reveal {
            opacity: 0; transform: translateY(16px);
            transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
            transition-delay: calc(var(--i, 0) * 80ms);
        }
        html.js .reveal.is-visible { opacity: 1; transform: none; }

        /* Hover lift for landing cards (transform only — no layout shift) */
        .lift { transition: transform 200ms ease, box-shadow 200ms ease, border-color 200ms ease; }
        .lift:hover { transform: translateY(-4px); box-shadow: 0 1px 2px rgb(var(--c-shadow) / .04), 0 16px 32px -12px rgb(var(--c-shadow) / .18); }

        /* Register steps: connector draws, numbers light up in sequence */
        .step-line { transform: scaleX(0); transform-origin: left; transition: transform 700ms var(--ease-out) 150ms; }
        .steps.is-visible .step-line { transform: scaleX(1); }
        .step-dot { transition: background-color 300ms ease, color 300ms ease, box-shadow 300ms ease; transition-delay: calc(150ms + var(--i, 0) * 175ms); }
        html.js .steps:not(.is-visible) .step-dot { background: rgb(var(--c-muted)); color: rgb(var(--c-muted-fg)); /* MASTER muted / muted-fg */ box-shadow: none; }
        .steps.is-visible .step-dot { box-shadow: 0 0 0 6px rgb(var(--c-accent) / 0.12); }

        /* Primary CTA micro-interaction */
        .cta { transition: transform 200ms ease, box-shadow 200ms ease, opacity 200ms ease; }
        .cta:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -8px rgb(var(--c-accent) / 0.55); }
        .cta:active { transform: translateY(0) scale(0.98); box-shadow: none; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            html.js .hero-in, html.hero-go .hero-in, html.js .reveal { opacity: 1; transform: none; animation: none; transition: none; }
            .radar-ring, .hero-glow, .scroll-cue { animation: none; }
            .radar-ring { opacity: 0.35; }
            .radar-ring:nth-child(1) { transform: scale(0.4); }
            .radar-ring:nth-child(2) { transform: scale(0.7); }
            .radar-ring:nth-child(3) { transform: scale(1); }
            .step-line { transform: none; transition: none; }
            html.js .steps:not(.is-visible) .step-dot { background: var(--color-accent); color: rgb(var(--c-white)); }
            .step-dot { transition: none; }
            .lift:hover, .cta:hover, .cta:active { transform: none; }
        }
    </style>
</head>
@php
    $hotline = (string) config('sagip.sos.hotline_number');
    $holdSeconds = (int) config('sagip.sos.hold_milliseconds', 2000) / 1000;
    $cancelSeconds = (int) config('sagip.sos.cancel_window_seconds', 5);
    $user = auth()->user();
    $appHome = $user ? ($user->isResident() ? route('requests.create') : route('dashboard')) : null;

    $features = [
        ['siren', 'danger', 'Press-and-hold SOS',
            "Hold the SOS button for {$holdSeconds} seconds, pick what's happening, and your GPS location goes to the barangay. A {$cancelSeconds}-second cancel window stops accidental alerts, and it falls back to SMS when you're offline."],
        ['clipboard', 'accent', 'Report in your own words',
            'Describe the emergency the way you would say it. Each report is classified automatically and routed to the responder who handles that kind of incident.'],
        ['house', 'success', 'Evacuation centers',
            'See which evacuation centers are open, on standby or full, with their remaining capacity and contact numbers.'],
        ['bell', 'warning', 'Real-time updates',
            'Follow your request from submitted to resolved, with notifications as it is reviewed, assigned and closed.'],
        ['shield-check', 'navy', 'Verified accounts',
            'Barangay officials confirm every resident registration, which keeps false alarms down and responders focused on real emergencies.'],
        ['tag', 'alert', 'Vulnerability tags',
            'Tell responders ahead of time if your household includes a PWD, an elderly person, someone pregnant, or an infant or young child.'],
    ];

    $steps = [
        ['Create your account',
            'Register with your full name, a valid email, your mobile number, your complete home address and a password.'],
        ['Complete your resident profile',
            'Right after signing up you land on your profile. Add the details responders need, including vulnerability tags if they apply to your household.'],
        ['Wait for verification',
            'A barangay official reviews your registration. You can check your status any time; if it is not approved, visit the barangay hall with a valid ID.'],
        ['Start using SAGIP',
            'Once verified, you can file emergency reports, send an SOS with your location and track every request you make.'],
    ];

    $tiles = [
        'danger' => 'bg-danger/10 text-danger', 'accent' => 'bg-accent/10 text-accent', 'success' => 'bg-success/10 text-success',
        'warning' => 'bg-warning/10 text-warning', 'navy' => 'bg-navy/10 text-navy', 'alert' => 'bg-alert/10 text-alert',
    ];
    $primaryButton = 'cta inline-flex items-center justify-center gap-2 min-h-12 rounded-xl bg-accent px-6 py-3 font-bold text-white';
@endphp
<body id="top" class="bg-canvas text-ink antialiased"
      x-data="{ menuOpen: false, pastHero: false }"
      x-init="const hero = document.getElementById('hero-section'); const check = () => pastHero = window.scrollY > hero.offsetHeight - 64; check(); window.addEventListener('scroll', check, { passive: true })">
<x-landing-loader />

<a href="#about" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[1300] focus:rounded-xl focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">
    Skip to content
</a>

{{-- Header --}}
<header class="sticky top-0 z-50 bg-surface/95 backdrop-blur border-b transition-[box-shadow,border-color] duration-300"
        :class="pastHero ? 'shadow-card border-line' : 'border-transparent'">
    <div class="max-w-6xl mx-auto h-16 px-4 sm:px-8 flex items-center gap-6">
        <a href="#top" class="shrink-0"><x-brand-logo class="h-10 rounded-md" /></a>

        <nav class="hidden md:flex items-center gap-1 text-sm font-bold text-muted-fg" aria-label="Page sections">
            <a href="#about" class="px-3 py-2 rounded-lg hover:text-navy hover:bg-canvas transition-colors duration-200">About</a>
            <a href="#how-to-register" class="px-3 py-2 rounded-lg hover:text-navy hover:bg-canvas transition-colors duration-200">How to register</a>
            <a href="#developers" class="px-3 py-2 rounded-lg hover:text-navy hover:bg-canvas transition-colors duration-200">Developers</a>
            <a href="#contact" class="px-3 py-2 rounded-lg hover:text-navy hover:bg-canvas transition-colors duration-200">Contact</a>
        </nav>

        <button type="button" data-theme-toggle onclick="toggleTheme()" aria-label="Dark mode" aria-pressed="false" title="Toggle dark mode"
                class="ml-auto h-11 w-11 inline-flex items-center justify-center rounded-full text-navy hover:bg-canvas transition-colors duration-200 cursor-pointer">
            <x-icon name="moon" class="theme-icon-moon" />
            <x-icon name="sun" class="theme-icon-sun" />
        </button>

        <div class="hidden sm:flex items-center gap-2">
            @if($user)
                <a href="{{ $appHome }}" class="inline-flex items-center gap-2 min-h-11 rounded-xl bg-accent px-5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200">Go to my dashboard</a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 rounded-xl px-4 text-sm font-bold text-navy hover:bg-canvas transition-colors duration-200">Log in</a>
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-11 rounded-xl bg-accent px-5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200">Register</a>
            @endif
        </div>

        <button type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="mobile-menu"
                class="md:hidden h-11 w-11 inline-flex items-center justify-center rounded-full text-navy hover:bg-canvas cursor-pointer" aria-label="Toggle menu">
            <x-icon name="menu" x-show="!menuOpen" />
            <x-icon name="x" x-show="menuOpen" x-cloak />
        </button>
    </div>

    <div id="mobile-menu" x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @click="if ($event.target.closest('a')) menuOpen = false"
         class="md:hidden border-t border-line bg-surface px-4 py-3 space-y-1 text-base font-bold text-navy">
        <a href="#about" class="block px-3 py-3 rounded-xl hover:bg-canvas">About</a>
        <a href="#how-to-register" class="block px-3 py-3 rounded-xl hover:bg-canvas">How to register</a>
        <a href="#developers" class="block px-3 py-3 rounded-xl hover:bg-canvas">Developers</a>
        <a href="#contact" class="block px-3 py-3 rounded-xl hover:bg-canvas">Contact</a>
        <div class="grid grid-cols-2 gap-2 pt-2 sm:hidden">
            @if($user)
                <a href="{{ $appHome }}" class="col-span-2 {{ $primaryButton }}">Go to my dashboard</a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center min-h-12 rounded-xl border border-line font-bold">Log in</a>
                <a href="{{ route('register') }}" class="{{ $primaryButton }}">Register</a>
            @endif
        </div>
    </div>
</header>

<main>
    {{-- 1. Hero --}}
    <section id="hero-section" class="relative overflow-hidden bg-navy text-white">
        <div class="absolute inset-0 bg-cover bg-bottom opacity-25" style="background-image: url('{{ asset('images/sagip-hero.jpg') }}')" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-navy via-navy/90 to-navy/40" aria-hidden="true"></div>

        {{-- Decorative motion: a slow accent glow --}}
        <div class="hero-glow absolute -top-1/4 right-[-10%] h-[36rem] w-[36rem] rounded-full bg-accent/20 blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-8 py-20 sm:py-28 lg:py-32 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] items-center gap-12 lg:gap-16">
            <div class="max-w-2xl">
                <p style="--i: 0" class="hero-in inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-sky-200">
                    <x-icon name="map-pin" class="w-4 h-4" /> Barangay Calatagan Tibang · Virac, Catanduanes
                </p>
                <h1 style="--i: 1" class="hero-in mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight">Help is one report away.</h1>
                <p style="--i: 2" class="hero-in mt-6 text-lg text-slate-300 leading-relaxed">
                    Barangay SAGIP is our barangay's emergency reporting and response system. Report an emergency in your
                    own words or send an SOS with your location, and it reaches the right responder in seconds. You can also
                    check which evacuation centers are open and follow your request until it is resolved.
                </p>
                <div style="--i: 3" class="hero-in mt-10 flex flex-col sm:flex-row gap-3">
                    @if($user)
                        <a href="{{ $appHome }}" class="{{ $primaryButton }}">Go to my dashboard <x-icon name="arrow-right" /></a>
                    @else
                        <a href="{{ route('register') }}" class="{{ $primaryButton }}">Register now <x-icon name="arrow-right" /></a>
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center min-h-12 rounded-xl bg-surface px-6 py-3 font-bold text-navy hover:bg-muted transition-colors duration-200">Log in</a>
                    @endif
                    <a href="#about" class="inline-flex items-center justify-center min-h-12 rounded-xl border border-white/25 px-6 py-3 font-bold text-white hover:bg-white/10 transition-colors duration-200">Learn how it works</a>
                </div>
                <p style="--i: 4" class="hero-in mt-8 text-sm text-slate-400">
                    In a life-threatening emergency, call the barangay hotline at
                    <a href="tel:{{ $hotline }}" class="font-bold text-white hover:underline">{{ $hotline }}</a>.
                </p>
            </div>

            {{-- Brand mark: the SAGIP shield on a light disc (its wordmark is dark), radar pulse radiating behind it --}}
            <div style="--i: 3" class="hero-in relative mx-auto w-64 sm:w-72 lg:w-[26rem] aspect-square pointer-events-none">
                <div class="absolute inset-0" aria-hidden="true">
                    <span class="radar-ring"></span><span class="radar-ring"></span><span class="radar-ring"></span>
                </div>
                <div class="absolute inset-[16%] rounded-full bg-white shadow-[0_0_48px_8px_rgb(var(--c-accent)/0.45)] ring-1 ring-white/20 flex items-center justify-center">
                    <img src="{{ asset('images/barangay-sagip-logo.svg') }}" alt="Barangay SAGIP emblem" class="h-[78%] w-auto" width="400" height="480">
                </div>
            </div>
        </div>
        <div class="absolute bottom-6 inset-x-0 hidden sm:flex justify-center">
            <a href="#about" class="hero-in inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/15 text-slate-300 hover:text-white hover:bg-white/10 transition-colors duration-200" style="--i: 5" aria-label="Scroll to About">
                <svg class="scroll-cue w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </a>
        </div>
    </section>

    {{-- 2. About / features --}}
    <section id="about" class="max-w-6xl mx-auto px-4 sm:px-8 py-20 sm:py-24">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-wider text-accent">About the system</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-navy">Faster help, better coordination</h2>
            <p class="mt-4 text-lg text-muted-fg leading-relaxed">
                SAGIP connects residents, barangay officials and response personnel in one place. Residents report
                emergencies without waiting on a phone line. Officials see every request as it comes in, and
                responders are matched by specialization and distance, so help arrives sooner.
            </p>
        </div>

        <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($features as [$icon, $tone, $title, $body])
                <div class="reveal" style="--i: {{ $loop->index }}">
                <article class="lift h-full bg-surface rounded-2xl border border-line shadow-card p-6">
                    <span class="h-12 w-12 rounded-xl inline-flex items-center justify-center {{ $tiles[$tone] }}"><x-icon :name="$icon" class="w-6 h-6" /></span>
                    <h3 class="mt-5 text-lg font-bold text-navy">{{ $title }}</h3>
                    <p class="mt-2 text-base text-muted-fg leading-relaxed">{{ $body }}</p>
                </article>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 3. How to register --}}
    <section id="how-to-register" class="bg-surface border-y border-line">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 py-20 sm:py-24">
            <div class="reveal max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-wider text-accent">How to register</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-navy">Four steps to get protected</h2>
                <p class="mt-4 text-lg text-muted-fg leading-relaxed">Registration is open to residents of Barangay Calatagan Tibang.</p>
            </div>

            <ol class="steps relative mt-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Connector: shows through the gaps between cards and draws in when the list enters view --}}
                <span class="hidden lg:block absolute top-12 left-12 right-12 h-0.5 bg-line" aria-hidden="true">
                    <span class="step-line block h-full bg-accent"></span>
                </span>
                @foreach ($steps as $index => [$title, $body])
                    <li class="reveal relative" style="--i: {{ $index }}">
                    <div class="lift relative z-10 h-full bg-canvas rounded-2xl border border-line p-6">
                        <span class="step-dot h-12 w-12 rounded-full bg-accent text-white text-xl font-bold inline-flex items-center justify-center shadow-card" style="--i: {{ $index }}" aria-hidden="true">{{ $index + 1 }}</span>
                        <h3 class="mt-5 text-lg font-bold text-navy"><span class="sr-only">Step {{ $index + 1 }}: </span>{{ $title }}</h3>
                        <p class="mt-2 text-base text-muted-fg leading-relaxed">{{ $body }}</p>
                    </div>
                    </li>
                @endforeach
            </ol>

            <div class="reveal mt-12 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                @if($user)
                    <a href="{{ $appHome }}" class="{{ $primaryButton }}">Go to my dashboard <x-icon name="arrow-right" /></a>
                @else
                    <a href="{{ route('register') }}" class="{{ $primaryButton }}">Register now <x-icon name="arrow-right" /></a>
                    <p class="text-sm text-muted-fg">Already registered? <a href="{{ route('login') }}" class="font-bold text-accent hover:underline">Log in</a></p>
                @endif
            </div>
        </div>
    </section>

    {{-- 4. Developers --}}
    <section id="developers" class="max-w-6xl mx-auto px-4 sm:px-8 py-20 sm:py-24">
        <div class="reveal max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-wider text-accent">The team</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold text-navy">Meet the developers</h2>
        </div>

        <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach (range(1, 4) as $developer)
                {{-- Placeholder card: replace the name, role and avatar with the real team member's. --}}
                <div class="reveal" style="--i: {{ $loop->index }}">
                <article class="lift h-full bg-surface rounded-2xl border border-line shadow-card p-6 text-center hover:border-accent/30">
                    <span class="mx-auto h-24 w-24 rounded-full bg-muted text-slate-400 inline-flex items-center justify-center" role="img" aria-label="Placeholder avatar">
                        <x-icon name="user" class="w-10 h-10" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold text-navy">Developer Name</h3>
                    <p class="mt-1 text-sm text-muted-fg">Role / Position</p>
                    <div class="mt-5 flex items-center justify-center gap-2" aria-label="Contact links (placeholders)">
                        @foreach (['mail' => 'Email', 'globe' => 'Website'] as $icon => $label)
                            <span title="{{ $label }} (placeholder)" class="h-10 w-10 rounded-full bg-canvas border border-line text-slate-400 inline-flex items-center justify-center">
                                <x-icon :name="$icon" class="w-4 h-4" :aria-label="$label . ' (placeholder)'" />
                            </span>
                        @endforeach
                    </div>
                </article>
                </div>
            @endforeach
        </div>
    </section>
</main>

{{-- 5. Footer --}}
<footer id="contact" class="bg-navy text-slate-300">
    <div class="max-w-6xl mx-auto px-4 sm:px-8 py-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
        <div class="lg:col-span-2">
            <x-brand-logo class="h-12 rounded-md" />
            <p class="mt-4 max-w-sm text-sm leading-relaxed">
                The emergency and assistance line for Barangay Calatagan Tibang, Virac, Catanduanes.
            </p>
        </div>

        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Contact</h2>
            <ul class="mt-4 space-y-3 text-sm">
                <li class="flex items-start gap-2.5"><x-icon name="map-pin" class="w-4 h-4 mt-0.5 text-sky-200" /> Barangay Hall, Calatagan Tibang, Virac, Catanduanes</li>
                <li class="flex items-start gap-2.5"><x-icon name="phone" class="w-4 h-4 mt-0.5 text-sky-200" />
                    <span>Hotline: <a href="tel:{{ $hotline }}" class="font-bold text-white hover:underline">{{ $hotline }}</a></span>
                </li>
            </ul>
        </div>

        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Quick links</h2>
            <ul class="mt-4 space-y-2 text-sm font-bold">
                <li><a href="#top" class="hover:text-white hover:underline">Home</a></li>
                <li><a href="#about" class="hover:text-white hover:underline">About</a></li>
                <li><a href="{{ route('register') }}" class="hover:text-white hover:underline">Register</a></li>
                <li><a href="#contact" class="hover:text-white hover:underline">Contact</a></li>
                <li class="pt-2"><a href="{{ route('admin.login') }}" class="text-slate-400 hover:text-white hover:underline">Staff login</a></li>
                <li><a href="{{ route('personnel.login') }}" class="text-slate-400 hover:text-white hover:underline">Responder login</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 py-6 text-xs text-slate-400">
            <p>&copy; {{ now()->year }} Barangay SAGIP · Barangay Calatagan Tibang. All rights reserved.</p>
        </div>
    </div>
</footer>
<script>
    (function () {
        const root = document.documentElement;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const targets = document.querySelectorAll('.reveal, .steps');

        // Hero entrance: start as the splash begins to fade (or now, if there is none).
        const startHero = () => root.classList.add('hero-go');
        const splash = document.getElementById('sg-splash');
        if (!splash || reduceMotion) {
            startHero();
        } else {
            new MutationObserver((changes, observer) => {
                if (!document.body.contains(splash) || splash.classList.contains('is-leaving')) {
                    startHero();
                    observer.disconnect();
                }
            }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
            setTimeout(startHero, 3500); // never later than the splash's own cap
        }

        // Scroll reveal — once per element.
        if (reduceMotion || !('IntersectionObserver' in window)) {
            targets.forEach(el => el.classList.add('is-visible'));
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
        targets.forEach(el => observer.observe(el));
    })();
</script>
</body>
</html>
