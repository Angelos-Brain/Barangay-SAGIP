<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Barangay SAGIP')</title>
    <x-design-tokens />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
@php
    $pageTitle = \Illuminate\Support\Str::before(trim($__env->yieldContent('title', 'Barangay SAGIP')), ' — ');
@endphp
<body class="bg-canvas text-ink min-h-screen antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
<x-splash-screen />

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[1300] focus:rounded-xl focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">
    Skip to main content
</a>

@auth
    @php
        $authUser = auth()->user();
        $isResident = $authUser->isResident();
        $unreadCount = $authUser->unreadNotifications()->count();
        $notificationsLabel = 'Notifications' . ($unreadCount ? ", {$unreadCount} unread" : '');
    @endphp

    {{-- Mobile drawer backdrop --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
         class="fixed inset-0 z-[1140] bg-navy/50 lg:hidden"></div>

    {{-- Sidebar: the one navigation for every authenticated screen --}}
    <aside class="fixed inset-y-0 left-0 z-[1150] w-64 bg-navy text-white flex flex-col transition-transform duration-200
                  -translate-x-full lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           aria-label="Main navigation">
        <div class="h-16 px-5 flex items-center justify-between border-b border-white/10 shrink-0">
            <a href="{{ $isResident ? route('requests.create') : route('dashboard') }}" class="flex items-center gap-2.5 font-bold text-lg">
                <span class="h-9 w-9 rounded-xl bg-accent inline-flex items-center justify-center"><x-icon name="shield-check" /></span>
                Barangay SAGIP
            </a>
            <button type="button" @click="sidebarOpen = false" class="lg:hidden h-10 w-10 -mr-2 inline-flex items-center justify-center rounded-full hover:bg-white/10 cursor-pointer" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            @if($isResident)
                <div class="space-y-1">
                    <x-nav-link :href="route('requests.create')" icon="siren" active="requests.create">Report Emergency</x-nav-link>
                    <x-nav-link :href="route('requests.index')" icon="clipboard" :active="['requests.index', 'requests.show']">My Requests</x-nav-link>
                    <x-nav-link :href="route('evacuation-centers.index')" icon="house" active="evacuation-centers.*">Evacuation Centers</x-nav-link>
                </div>
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Account</p>
                    <x-nav-link :href="route('notifications.index')" icon="bell" active="notifications.*">Notifications</x-nav-link>
                    <x-nav-link :href="route('residents.profile.edit')" icon="user" active="residents.profile.*">Profile</x-nav-link>
                </div>
            @else
                <div class="space-y-1">
                    <x-nav-link :href="route('dashboard')" icon="dashboard" active="dashboard">Dashboard</x-nav-link>
                    <x-nav-link :href="route('requests.index')" icon="clipboard" active="requests.*">Requests</x-nav-link>
                    <x-nav-link :href="route('map.index')" icon="map" active="map.*">Live Map</x-nav-link>
                    <x-nav-link :href="route('evacuation-centers.index')" icon="house" active="evacuation-centers.*">Evacuation Centers</x-nav-link>
                </div>

                @if($authUser->isOfficialOrAdmin() || $authUser->can(\App\Enums\Permission::AuditView->value))
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Administration</p>
                        @if($authUser->isOfficialOrAdmin())
                            <x-nav-link :href="route('personnel.index')" icon="users" :active="['personnel.index', 'personnel.create', 'personnel.edit']">Personnel</x-nav-link>
                            <x-nav-link :href="route('verifications.index')" icon="user-check" active="verifications.*">Verifications</x-nav-link>
                            <x-nav-link :href="route('reports.index')" icon="chart" active="reports.*">Reports</x-nav-link>
                        @endif
                        @can(\App\Enums\Permission::AuditView->value)
                            <x-nav-link :href="route('audit.index')" icon="history" active="audit.*">Audit Log</x-nav-link>
                        @endcan
                    </div>
                @endif

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Account</p>
                    @if($authUser->isPersonnel())
                        <x-nav-link :href="route('personnel.specializations.edit')" icon="tag" active="personnel.specializations.*">My Tags</x-nav-link>
                    @endif
                    <x-nav-link :href="route('notifications.index')" icon="bell" active="notifications.*">Notifications</x-nav-link>
                    <x-nav-link :href="route('account.photo.edit')" icon="camera" active="account.photo.*">My Photo</x-nav-link>
                </div>
            @endif
        </nav>

        <div class="p-3 border-t border-white/10 shrink-0">
            <form method="POST" action="{{ $isResident ? route('logout') : route('admin.logout') }}">
                @csrf
                <button class="w-full flex items-center gap-3 min-h-11 px-3 py-2.5 rounded-xl text-sm font-bold text-slate-300 hover:bg-white/5 hover:text-white transition-colors duration-200 cursor-pointer">
                    <x-icon name="log-out" />
                    Logout
                </button>
            </form>
        </div>
    </aside>
@endauth

<div class="min-h-screen flex flex-col @auth lg:pl-64 @endauth">
    @auth
        {{-- Top bar --}}
        <header class="sticky top-0 z-[1050] h-16 bg-surface border-b border-line">
            <div class="h-full px-4 sm:px-8 flex items-center gap-3">
                <button type="button" @click="sidebarOpen = true" :aria-expanded="sidebarOpen"
                        class="lg:hidden h-10 w-10 -ml-2 inline-flex items-center justify-center rounded-full text-navy hover:bg-canvas cursor-pointer" aria-label="Open menu">
                    <x-icon name="menu" />
                </button>

                <p class="text-lg font-bold text-navy truncate">{{ $pageTitle }}</p>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <button type="button" data-theme-toggle onclick="toggleTheme()" aria-label="Dark mode" aria-pressed="false" title="Toggle dark mode"
                            class="h-10 w-10 inline-flex items-center justify-center rounded-full bg-canvas text-navy hover:bg-muted transition-colors duration-200 cursor-pointer">
                        <x-icon name="moon" class="theme-icon-moon" />
                        <x-icon name="sun" class="theme-icon-sun" />
                    </button>
                    @if($isResident)
                        <a href="{{ route('requests.create') }}" title="Report an emergency"
                           class="h-10 w-10 inline-flex items-center justify-center rounded-full bg-canvas text-navy hover:bg-muted transition-colors duration-200">
                            <x-icon name="siren" aria-label="Report an emergency" />
                        </a>
                    @else
                        <a href="{{ route('map.index') }}" title="Live map"
                           class="h-10 w-10 inline-flex items-center justify-center rounded-full bg-canvas text-navy hover:bg-muted transition-colors duration-200">
                            <x-icon name="map" aria-label="Live map" />
                        </a>
                    @endif
                    <a href="{{ route('notifications.index') }}" title="Notifications"
                       class="relative h-10 w-10 inline-flex items-center justify-center rounded-full bg-canvas text-navy hover:bg-muted transition-colors duration-200">
                        <x-icon name="bell" :aria-label="$notificationsLabel" />
                        @if($unreadCount)
                            <span class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-danger text-white text-[11px] font-bold inline-flex items-center justify-center ring-2 ring-surface">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ $isResident ? route('residents.profile.edit') : route('account.photo.edit') }}"
                       class="flex items-center gap-2.5 pl-1 sm:pl-2 rounded-full" title="Your profile">
                        <x-avatar :user="$authUser" size="h-10 w-10 text-sm" class="ring-2 ring-accent/20" />
                        <span class="hidden sm:block leading-tight">
                            <span class="block text-sm font-bold text-navy">{{ $authUser->name }}</span>
                            <span class="block text-xs text-muted-fg">{{ $authUser->role?->label() }}</span>
                        </span>
                    </a>
                </div>
            </div>
        </header>
    @endauth

    <main id="main" class="flex-1 w-full max-w-[1440px] mx-auto px-4 sm:px-8 py-6 sm:py-8">
        {{-- Feature 9: role-aware back navigation on every authenticated screen. --}}
        @auth
            <x-back-link />
        @endauth

        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-2xl bg-success/10 border border-success/20 text-success px-4 py-3 text-sm font-bold" role="status">
                <x-icon name="check-circle" class="w-5 h-5 mt-px" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-2xl bg-danger/10 border border-danger/20 text-danger px-4 py-3 text-sm" role="alert">
                <x-icon name="alert" class="w-5 h-5 mt-px" />
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

@auth
    {{-- Feature 2: persistent SOS control on every authenticated page. --}}
    @if(auth()->user()->isVerified() && auth()->user()->can(\App\Enums\Permission::SosTrigger->value))
        <x-sos-button />
    @endif
@endauth

@stack('scripts')
</body>
</html>
