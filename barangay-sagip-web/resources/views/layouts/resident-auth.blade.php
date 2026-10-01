<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Barangay SAGIP')</title>
    <x-design-tokens />
    <style>
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px rgb(var(--c-surface)) inset !important;
            -webkit-text-fill-color: rgb(var(--c-ink)) !important;
        }
    </style>
    @stack('head')
</head>
<body class="bg-canvas text-ink min-h-screen antialiased">
<x-splash-screen />
    <div class="min-h-screen lg:flex">

        {{-- Left: branding panel (same navy as the app sidebar) — hidden on small screens so the form is reachable without scrolling --}}
        <div class="relative hidden lg:flex lg:w-[44%] overflow-hidden bg-navy bg-cover bg-bottom" style="background-image: url('{{ asset('images/sagip-hero.jpg') }}')">
            <div class="absolute inset-0 bg-navy/85"></div>

            <div class="relative z-10 flex flex-col h-full p-10 xl:p-14 w-full text-white">
                <div class="flex items-center gap-2.5 font-bold text-lg">
                    <span class="h-10 w-10 rounded-xl bg-accent inline-flex items-center justify-center"><x-icon name="shield-check" /></span>
                    Barangay SAGIP
                </div>

                <div class="my-auto pt-10">
                    <h1 class="text-4xl font-bold leading-tight max-w-md">
                        Help is one report away.
                    </h1>
                    <p class="mt-4 text-slate-300 max-w-md leading-relaxed">
                        The emergency and assistance line for Barangay Calatagan Tibang, Virac, Catanduanes —
                        submit a report and get matched to the right responder in seconds.
                    </p>

                    <div class="mt-10 space-y-3 max-w-md">
                        @foreach ([
                            ['siren', 'Report in your own words', 'No forms to fill out mid-emergency — just describe what\'s happening.'],
                            ['users', 'Matched automatically', 'Your report is classified and routed to the right responder.'],
                            ['activity', 'Tracked in real time', 'Follow your request from submitted to resolved.'],
                        ] as [$icon, $title, $body])
                            <div class="flex items-start gap-3 rounded-2xl bg-white/5 border border-white/10 p-4">
                                <span class="h-10 w-10 rounded-xl bg-accent/20 text-sky-200 inline-flex items-center justify-center shrink-0"><x-icon :name="$icon" /></span>
                                <div>
                                    <p class="text-sm font-bold">{{ $title }}</p>
                                    <p class="text-sm text-slate-300">{{ $body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: form panel --}}
        <main class="relative flex-1 flex items-center justify-center px-4 sm:px-6 py-10">
            <button type="button" data-theme-toggle onclick="toggleTheme()" aria-label="Dark mode" aria-pressed="false" title="Toggle dark mode"
                    class="absolute top-4 right-4 h-11 w-11 inline-flex items-center justify-center rounded-full bg-surface border border-line text-navy hover:bg-muted transition-colors duration-200 cursor-pointer">
                <x-icon name="moon" class="theme-icon-moon" />
                <x-icon name="sun" class="theme-icon-sun" />
            </button>

            <div class="w-full max-w-md">

                <div class="lg:hidden flex items-center justify-center mb-8">
                    <img src="{{ asset('images/sagip-logo.png') }}" alt="Barangay SAGIP" class="h-11 w-auto rounded-md">
                </div>

                <div class="bg-surface rounded-2xl border border-line shadow-card p-6 sm:p-8">
                    @if (session('status'))
                        <div class="mb-6 flex items-start gap-3 rounded-xl bg-success/10 border border-success/20 text-success px-4 py-3 text-sm font-bold" role="status">
                            <x-icon name="check-circle" class="mt-px" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 flex items-start gap-3 rounded-xl bg-danger/10 border border-danger/20 text-danger px-4 py-3 text-sm" role="alert">
                            <x-icon name="alert" class="mt-px" />
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
