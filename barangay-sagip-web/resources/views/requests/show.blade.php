@extends('layouts.app')
@section('title', 'Request #' . $emergencyRequest->id . ' — Barangay SAGIP')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 min-w-0 bg-surface rounded-lg shadow p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div class="flex items-start gap-3">
                <x-avatar :user="$emergencyRequest->resident" size="h-10 w-10 text-sm" />
                <div>
                <h1 class="text-xl font-bold text-navy">Request #{{ $emergencyRequest->id }}</h1>
                <p class="text-sm text-gray-500">
                    Submitted {{ $emergencyRequest->created_at->format('M j, Y g:i A') }}
                    by {{ $emergencyRequest->resident->name }}
                </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($emergencyRequest->urgency)
                    <x-badge :color="$emergencyRequest->urgency->badgeColor()">{{ $emergencyRequest->urgency->label() }} Urgency</x-badge>
                @endif
                <x-badge :color="$emergencyRequest->status->badgeColor()">{{ $emergencyRequest->status->label() }}</x-badge>
            </div>
        </div>

        <p class="bg-gray-50 rounded-md p-3 text-sm mb-4 break-words">{{ $emergencyRequest->description }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-6">
            <div>
                <span class="text-gray-500">Category:</span>
                <span class="font-medium capitalize">{{ str_replace('_', ' ', $emergencyRequest->category ?? '—') }}</span>
                @if($emergencyRequest->category_confidence)
                    <span class="text-gray-400">({{ number_format($emergencyRequest->category_confidence * 100, 1) }}% confidence)</span>
                @endif
            </div>
            <div>
                <span class="text-gray-500">Urgency:</span>
                <span class="font-medium capitalize">{{ $emergencyRequest->urgency?->label() ?? '—' }}</span>
                @if($emergencyRequest->urgency_confidence)
                    <span class="text-gray-400">({{ number_format($emergencyRequest->urgency_confidence * 100, 1) }}% confidence)</span>
                @endif
            </div>
        </div>

        @unless(auth()->user()->isResident())
            @php $residentProfile = $emergencyRequest->resident->residentProfile; @endphp
            @if($residentProfile?->hasVulnerableMembers())
                {{-- Feature 1: responders need to know who in the household needs priority help. --}}
                <div class="bg-purple-50 border border-purple-200 rounded-md p-3 mb-6 text-sm">
                    <span class="font-medium text-purple-900">Vulnerable household members:</span>
                    <span class="inline-flex flex-wrap gap-1 ml-1 align-middle">
                        @foreach($residentProfile->vulnerabilityTagEnums() as $tag)
                            <x-badge :color="$tag->badgeColor()">{{ $tag->shortLabel() }}</x-badge>
                        @endforeach
                    </span>
                </div>
            @endif
        @endunless

        @if($emergencyRequest->needs_review)
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm rounded-md p-3 mb-6">
                <strong>Flagged for review:</strong> {{ $emergencyRequest->review_reason }}
            </div>
        @endif

        @if($emergencyRequest->isSos())
            {{-- Feature 2: the reason and the corroborating data captured with the SOS. --}}
            <div class="bg-red-50 border border-red-200 rounded-md p-3 mb-6 text-sm space-y-1">
                <div>
                    <span class="text-red-900 font-medium">SOS reason:</span>
                    <span class="break-words">{{ $emergencyRequest->sosReasonLabel() ?? 'Not given' }}</span>
                </div>
                @unless(auth()->user()->isResident())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-700">
                        <div>
                            <span class="text-gray-500">GPS accuracy:</span>
                            {{ $emergencyRequest->location_accuracy_meters !== null ? '±'.number_format((float) $emergencyRequest->location_accuracy_meters, 0).' m' : 'Unknown' }}
                        </div>
                        <div>
                            <span class="text-gray-500">Account status at SOS:</span>
                            {{ $emergencyRequest->reporter_verification_status?->label() ?? '—' }}
                        </div>
                        <div>
                            <span class="text-gray-500">Device:</span>
                            <span class="font-mono break-all">{{ $emergencyRequest->device_id ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Reporter's false alarms:</span>
                            @php($falseAlarms = $emergencyRequest->resident->falseAlarmCount())
                            <span class="{{ $falseAlarms >= \App\Models\User::falseAlarmFlagThreshold() ? 'font-semibold text-red-700' : '' }}">{{ $falseAlarms }}</span>
                        </div>
                    </div>
                    @if($emergencyRequest->location_flags)
                        <div class="flex flex-wrap gap-1 pt-1">
                            @foreach($emergencyRequest->location_flags as $flag)
                                <x-badge color="yellow">{{ ucfirst(str_replace('_', ' ', $flag)) }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                @endunless
                @if($emergencyRequest->attachment_path)
                    <a href="{{ route('requests.attachment', $emergencyRequest) }}" target="_blank" rel="noopener"
                       class="inline-block text-xs text-accent hover:underline">View attached photo / voice note →</a>
                @endif
            </div>
        @endif

        @if($emergencyRequest->outcome)
            <div class="mb-6 text-sm">
                <span class="text-gray-500">Outcome:</span>
                <x-badge :color="$emergencyRequest->outcome->badgeColor()">{{ $emergencyRequest->outcome->label() }}</x-badge>
            </div>
        @endif

        @if($assignment = $emergencyRequest->currentAssignment)
            <div class="bg-indigo-50 border border-indigo-200 rounded-md p-3 mb-6 text-sm">
                @if(auth()->user()->isResident())
                    <strong>Response team:</strong> A responder has been assigned to your request.
                @else
                    <x-avatar :user="$assignment->responsePersonnel->user" :name="$assignment->responsePersonnel->name" size="h-6 w-6 text-[10px]" class="align-middle mr-1" />
                    <strong>Assigned to:</strong> {{ $assignment->responsePersonnel->name }}
                    ({{ str_replace('_', ' ', $assignment->responsePersonnel->specialization) }})
                    @if($assignment->distance_km)
                        — {{ $assignment->distance_km }} km away
                    @endif
                    @if($assignment->was_manual_override)
                        <span class="text-gray-400">(manually assigned)</span>
                    @endif
                @endif
            </div>
        @endif

        <h2 class="font-semibold text-navy mb-2">Status Timeline</h2>
        <ol class="relative border-l border-gray-200 ml-2">
            @foreach ($emergencyRequest->statusLogs as $log)
                <li class="mb-4 ml-4">
                    <div class="absolute w-2 h-2 bg-accent rounded-full -left-1 mt-1.5"></div>
                    <time class="text-xs text-gray-400">{{ $log->created_at->format('M j, g:i A') }}</time>
                    <p class="text-sm font-medium capitalize">{{ str_replace('_', ' ', $log->status) }}</p>
                    @if($log->note)
                        <p class="text-xs text-gray-500">{{ $log->note }}</p>
                    @endif
                </li>
            @endforeach
        </ol>

        @if(auth()->user()->isOfficial() || auth()->user()->isPersonnel())
            @php($nextStatuses = collect([
                'validated' => 'Validated',
                'assigned' => 'Assigned',
                'en_route' => 'En Route',
                'resolved' => 'Resolved',
                'cancelled' => 'Cancelled',
            ])->filter(fn ($label, $status) => $emergencyRequest->canTransitionTo(\App\Enums\RequestStatus::from($status)))
                ->when(auth()->user()->isPersonnel() && $emergencyRequest->status === \App\Enums\RequestStatus::NeedsReview, fn ($statuses) => $statuses->except(['validated']))
            )

            @if($emergencyRequest->status === \App\Enums\RequestStatus::NeedsReview && auth()->user()->isPersonnel())
                <div class="border-t pt-4 mt-4">
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm rounded-md p-3">
                        This request is waiting for official validation. A barangay official must review it before operational status can be advanced.
                    </div>
                </div>
            @elseif($nextStatuses->isNotEmpty())
                <div class="border-t pt-4 mt-4">
                    <h2 class="font-semibold text-navy mb-2">Update Status</h2>
                    <form method="POST" action="{{ route('requests.updateStatus', $emergencyRequest) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-base sm:border-0 sm:p-0 sm:text-sm">
                            @foreach ($nextStatuses as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="note" placeholder="Optional note" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-base sm:border-0 sm:p-0 sm:text-sm">
                        <button class="bg-navy text-white text-sm rounded-md px-4 py-2.5 sm:py-0 hover:bg-accent transition">Update</button>
                    </form>
                </div>
            @endif

            @if(auth()->user()->isOfficial() && in_array($emergencyRequest->status, [\App\Enums\RequestStatus::Validated, \App\Enums\RequestStatus::Assigned], true))
                <a href="{{ route('requests.assign.edit', $emergencyRequest) }}" class="inline-block mt-3 py-2 sm:py-0 text-accent text-sm hover:underline">
                    Review / reassign responder →
                </a>
            @endif
        @endif

        @if($emergencyRequest->isHandledBy(auth()->user()))
            {{-- Feature 2: post-incident validation. --}}
            @if($emergencyRequest->canRecordOutcome())
                <div class="border-t pt-4 mt-4">
                    <h2 class="font-semibold text-navy mb-2">Incident Outcome</h2>
                    <form method="POST" action="{{ route('requests.updateOutcome', $emergencyRequest) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="outcome" aria-label="Incident outcome" class="rounded-md border border-gray-300 px-3 py-2 text-base sm:text-sm">
                            @foreach(\App\Enums\IncidentOutcome::cases() as $outcome)
                                <option value="{{ $outcome->value }}" @selected($emergencyRequest->outcome === $outcome)>{{ $outcome->label() }}</option>
                            @endforeach
                        </select>
                        <button class="bg-navy text-white text-sm rounded-md px-4 py-2.5 sm:py-2 hover:bg-accent transition">
                            {{ $emergencyRequest->outcome ? 'Change outcome' : 'Record outcome' }}
                        </button>
                    </form>
                    @error('outcome')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @elseif($emergencyRequest->isSos())
                {{-- Feature 2: lift the reporter's cooldown mid-response. --}}
                <div class="border-t pt-4 mt-4">
                    <form method="POST" action="{{ route('requests.clearSosCooldown', $emergencyRequest) }}"
                          onsubmit="return confirm('Allow this resident to send another SOS immediately?')">
                        @csrf
                        <button class="text-sm text-accent hover:underline">Clear this resident's SOS cooldown</button>
                    </form>
                    @error('cooldown')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endif
        @endif
    </div>

    <div class="bg-surface rounded-lg shadow p-4">
        <h2 class="font-semibold text-navy mb-2 text-sm">Location</h2>
        <div id="detail-map" class="w-full h-56 rounded-md border"></div>
    </div>
</div>

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush
@push('scripts')
<script>
    const dmap = L.map('detail-map').setView([{{ $emergencyRequest->latitude }}, {{ $emergencyRequest->longitude }}], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dmap);
    L.marker([{{ $emergencyRequest->latitude }}, {{ $emergencyRequest->longitude }}]).addTo(dmap);
</script>
@endpush
@endsection
