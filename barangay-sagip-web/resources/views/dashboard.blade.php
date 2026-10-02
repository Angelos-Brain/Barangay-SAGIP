@extends('layouts.app')
@section('title', 'Dashboard — Barangay SAGIP')

@php
    $th = 'px-5 py-3 text-xs font-bold uppercase tracking-wide';
    $td = 'px-5 py-3';
@endphp

@section('content')

@if($role === 'personnel')
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('account.photo.edit') }}" title="Change your photo"><x-avatar :user="auth()->user()" size="h-14 w-14 text-lg" class="ring-4 ring-surface shadow-card" /></a>
        <div>
            <h1 class="text-2xl font-bold text-navy">Welcome, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-muted-fg mt-0.5">Your active assignments.</p>
        </div>
    </div>

    @if(!$personnel)
        <div class="flex items-start gap-3 rounded-2xl bg-warning/10 border border-warning/20 text-warning text-sm p-4">
            <x-icon name="alert" class="mt-px" />
            <p>Your account isn't linked to a personnel record yet. Ask a barangay official to link it in
            Response Personnel Management.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6">
            <x-stat-card icon="clipboard" label="Active assignments" :value="$assignments->count()" tone="accent" />
            <x-stat-card icon="{{ $personnel->is_available ? 'check-circle' : 'clock' }}" label="Status"
                         :value="$personnel->is_available ? 'Available' : 'Unavailable'"
                         :tone="$personnel->is_available ? 'success' : 'navy'" />
            <x-stat-card icon="tag" label="Specializations" :value="count($personnel->specializationEnums())"
                         :hint="collect($personnel->specializationEnums())->map->label()->implode(', ') ?: null" tone="navy" />
        </div>

        @if(auth()->user()->isTanod())
            {{-- Feature 7: a tanod's duty status is geofenced to the barangay hall. --}}
            <x-tanod-duty-panel :personnel="$personnel" />
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-card title="Active Assignments" subtitle="Incidents currently routed to you." :padding="false" class="lg:col-span-2">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-canvas text-muted-fg text-left">
                        <tr><th class="{{ $th }}">Request</th><th class="{{ $th }}">Category</th><th class="{{ $th }}">Urgency</th><th class="{{ $th }}">Distance</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($assignments as $a)
                            <tr class="hover:bg-canvas transition-colors duration-150">
                                <td class="{{ $td }} font-bold text-navy">#{{ $a->emergencyRequest->id }}</td>
                                <td class="{{ $td }} capitalize">{{ str_replace('_', ' ', $a->emergencyRequest->category) }}</td>
                                <td class="{{ $td }}"><x-badge :color="$a->emergencyRequest->urgency?->badgeColor()">{{ $a->emergencyRequest->urgency?->label() }}</x-badge></td>
                                <td class="{{ $td }} tabular-nums">{{ $a->distance_km ? $a->distance_km . ' km' : '—' }}</td>
                                <td class="{{ $td }} text-right"><a href="{{ route('requests.show', $a->emergencyRequest) }}" class="font-bold text-accent hover:underline">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-muted-fg">No active assignments right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </x-card>

            <x-card title="My Availability">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-fg">
                        {{ $personnel->is_available
                            ? 'You can receive new assignments.'
                            : 'You will not receive new assignments until you mark yourself available.' }}
                    </p>
                    <x-badge :color="$personnel->is_available ? 'green' : 'gray'" class="shrink-0">
                        {{ $personnel->is_available ? 'Available' : 'Unavailable' }}
                    </x-badge>
                </div>

                @if($personnel->is_available)
                    <form method="POST" action="{{ route('personnel.updateOwnAvailability') }}" class="mt-5 space-y-3">
                        @csrf
                        <input type="hidden" name="is_available" value="0">
                        <div>
                            <label for="unavailability_reason" class="block text-sm font-bold text-navy">
                                Reason for being unavailable
                            </label>
                            <textarea id="unavailability_reason" name="unavailability_reason" rows="3" maxlength="500" required
                                      @error('unavailability_reason') aria-invalid="true" aria-describedby="unavailability_reason-error" @enderror
                                      placeholder="e.g. Off duty, sick leave, attending training…"
                                      class="mt-1.5 w-full rounded-xl border border-line px-4 py-3 text-base transition-colors duration-200 focus:border-navy focus:outline-none focus:ring-[3px] focus:ring-navy/10">{{ old('unavailability_reason') }}</textarea>
                            @error('unavailability_reason')
                                <p id="unavailability_reason-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <button class="w-full min-h-11 rounded-xl bg-secondary hover:bg-navy text-white text-sm font-bold px-4 py-2.5 transition-colors duration-200 cursor-pointer">
                            Mark as Unavailable
                        </button>
                    </form>
                @else
                    @if($personnel->unavailability_reason)
                        <p class="mt-4 text-sm text-navy bg-canvas border border-line rounded-xl px-4 py-3">
                            <span class="font-bold">Reason:</span> {{ $personnel->unavailability_reason }}
                        </p>
                    @endif
                    <form method="POST" action="{{ route('personnel.updateOwnAvailability') }}" class="mt-5">
                        @csrf
                        <input type="hidden" name="is_available" value="1">
                        <button class="w-full min-h-11 rounded-xl bg-success hover:opacity-90 text-white text-sm font-bold px-4 py-2.5 transition-opacity duration-200 cursor-pointer">
                            Mark as Available
                        </button>
                    </form>
                @endif
            </x-card>
        </div>
    @endif

@else
    {{-- official --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-navy">Barangay Operations Overview</h1>
            <p class="text-sm text-muted-fg mt-0.5">Live status of requests, responders and review queues.</p>
        </div>
        <a href="{{ route('map.index') }}" class="inline-flex items-center gap-2 min-h-11 rounded-xl bg-accent px-5 py-2.5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200">
            <x-icon name="map" /> Open live map
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 sm:gap-6 mb-6">
        <x-stat-card icon="clipboard" label="Total Requests" :value="$counts['total']" tone="navy" />
        <x-stat-card icon="flag" label="Needs Review" :value="$counts['needs_review']" tone="warning" />
        <x-stat-card icon="siren" label="Critical & Open" :value="$counts['critical_open']" tone="danger" />
        <x-stat-card icon="check-circle" label="Resolved Today" :value="$counts['resolved_today']" tone="success" />
        <x-stat-card icon="user-check" label="Available Personnel" :value="$counts['available_personnel']" tone="accent" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6 min-w-0">
            @if($needsReviewRequests->isNotEmpty())
                <x-card title="Requests Needing Official Review" subtitle="These requests were flagged by classification and must be validated before assignment." :padding="false" class="border-warning/30">
                    <x-slot:actions><x-badge color="yellow" class="whitespace-nowrap">{{ $counts['needs_review'] }} pending</x-badge></x-slot:actions>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-canvas text-muted-fg text-left">
                                <tr><th class="{{ $th }}">#</th><th class="{{ $th }}">Resident</th><th class="{{ $th }}">Category</th><th class="{{ $th }}">Urgency</th><th class="{{ $th }}">Reason</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($needsReviewRequests as $r)
                                    <tr class="hover:bg-canvas transition-colors duration-150">
                                        <td class="{{ $td }} font-bold text-navy">#{{ $r->id }}</td>
                                        <td class="{{ $td }}"><div class="flex items-center gap-2"><x-avatar :user="$r->resident" size="h-8 w-8 text-xs" /><span>{{ $r->resident->name }}</span></div></td>
                                        <td class="{{ $td }} capitalize">{{ str_replace('_', ' ', $r->category ?? '—') }}</td>
                                        <td class="{{ $td }}">
                                            @if($r->urgency)<x-badge :color="$r->urgency->badgeColor()">{{ $r->urgency->label() }}</x-badge>@else — @endif
                                        </td>
                                        <td class="{{ $td }} text-xs text-muted-fg max-w-xs">{{ $r->review_reason ?? 'Manual validation required.' }}</td>
                                        <td class="{{ $td }} text-right"><a href="{{ route('requests.show', $r) }}" class="font-bold text-accent hover:underline whitespace-nowrap">Review →</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            <x-card title="Recent Requests" :padding="false">
                <x-slot:actions><a href="{{ route('requests.index') }}" class="text-sm font-bold text-accent hover:underline">View all</a></x-slot:actions>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-canvas text-muted-fg text-left">
                        <tr><th class="{{ $th }}">#</th><th class="{{ $th }}">Resident</th><th class="{{ $th }}">Category</th><th class="{{ $th }}">Urgency</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Assigned</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($recentRequests as $r)
                            <tr class="hover:bg-canvas transition-colors duration-150">
                                <td class="{{ $td }} font-bold text-navy">#{{ $r->id }}</td>
                                <td class="{{ $td }}"><div class="flex items-center gap-2"><x-avatar :user="$r->resident" size="h-8 w-8 text-xs" /><span>{{ $r->resident->name }}</span></div></td>
                                <td class="{{ $td }} capitalize">{{ str_replace('_', ' ', $r->category ?? '—') }}</td>
                                <td class="{{ $td }}">
                                    @if($r->urgency)<x-badge :color="$r->urgency->badgeColor()">{{ $r->urgency->label() }}</x-badge>@else — @endif
                                </td>
                                <td class="{{ $td }}"><x-badge :color="$r->status->badgeColor()">{{ $r->status->label() }}</x-badge></td>
                                <td class="{{ $td }}">{{ $r->currentAssignment->responsePersonnel->name ?? '—' }}</td>
                                <td class="{{ $td }} text-right"><a href="{{ route('requests.show', $r) }}" class="font-bold text-accent hover:underline">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-muted-fg">No requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6 min-w-0">
            @php $categoryTotal = $categoryBreakdown->sum(); @endphp
            <x-card title="Request Mix" subtitle="Share of classified requests by category.">
                @forelse ($categoryBreakdown->sortDesc() as $category => $total)
                    <x-meter :label="\Illuminate\Support\Str::of($category)->replace('_', ' ')->title()" :value="$total" :max="$categoryTotal"
                             :display="$total . ' · ' . round($total / max(1, $categoryTotal) * 100) . '%'" tone="accent"
                             class="{{ $loop->first ? '' : 'mt-4' }}" />
                @empty
                    <p class="text-sm text-muted-fg">No classified requests yet.</p>
                @endforelse
            </x-card>

            @if($flaggedAccounts->isNotEmpty())
                {{-- Feature 2: repeat false alarms are surfaced for a human decision — never auto-suspended. --}}
                <x-card title="Accounts Flagged for Review" class="border-danger/30"
                        subtitle="{{ \App\Models\User::falseAlarmFlagThreshold() }} or more incidents closed as a false alarm. No action has been taken on these accounts.">
                    <ul class="divide-y divide-line -my-3">
                        @foreach ($flaggedAccounts as $account)
                            <li class="py-3 flex items-center gap-3">
                                <x-avatar :user="$account" size="h-9 w-9 text-xs" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-navy truncate">{{ $account->name }}</p>
                                    <p class="text-xs text-muted-fg break-all">{{ $account->email }}</p>
                                    @if($account->verification_status)
                                        <x-badge :color="$account->verification_status->badgeColor()" class="mt-1">{{ $account->verification_status->label() }}</x-badge>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-lg font-bold text-danger tabular-nums">{{ $account->false_alarm_count }}</p>
                                    <p class="text-[11px] text-muted-fg">false alarms</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
@endif

@endsection
