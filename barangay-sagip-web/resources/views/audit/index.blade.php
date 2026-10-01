@extends('layouts.app')
@section('title', 'Audit Log — Barangay SAGIP')

@php
    $th = 'px-5 py-3 text-xs font-bold uppercase tracking-wide';
    $td = 'px-5 py-3';
    $inputClass = 'w-full min-h-11 rounded-xl border border-line bg-surface px-3 text-sm transition-colors duration-200 focus:border-navy focus:outline-none focus:ring-[3px] focus:ring-navy/10';
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-navy">Audit Log</h1>
        <p class="text-sm text-muted-fg mt-0.5">
            Append-only record of every state-changing action. Administrator access only.
        </p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full bg-surface border border-line px-4 py-2 text-sm font-bold text-navy shadow-card">
        <x-icon name="history" class="w-4 h-4 text-accent" />
        {{ number_format($entries->total()) }} entries
    </span>
</div>

<x-card class="mb-6">
    <form method="GET" action="{{ route('audit.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
        <label class="block">
            <span class="block text-xs font-bold uppercase tracking-wide text-muted-fg mb-1.5">Action</span>
            <select name="action" class="{{ $inputClass }}">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-xs font-bold uppercase tracking-wide text-muted-fg mb-1.5">Search</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Actor or record" class="{{ $inputClass }}">
        </label>
        <label class="block">
            <span class="block text-xs font-bold uppercase tracking-wide text-muted-fg mb-1.5">From</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $inputClass }}">
        </label>
        <label class="block">
            <span class="block text-xs font-bold uppercase tracking-wide text-muted-fg mb-1.5">To</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $inputClass }}">
        </label>
        <div class="flex items-end gap-2">
            <button class="inline-flex items-center gap-2 min-h-11 rounded-xl bg-accent px-5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200 cursor-pointer">
                <x-icon name="filter" class="w-4 h-4" /> Filter
            </button>
            <a href="{{ route('audit.index') }}" class="inline-flex items-center min-h-11 px-3 text-sm font-bold text-muted-fg hover:underline">Reset</a>
        </div>
    </form>
</x-card>

<x-card title="Entries" :padding="false">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-canvas text-muted-fg text-left">
            <tr>
                <th class="{{ $th }}">When</th>
                <th class="{{ $th }}">Who</th>
                <th class="{{ $th }}">Action</th>
                <th class="{{ $th }}">Record</th>
                <th class="{{ $th }}">Before → After</th>
                <th class="{{ $th }}">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line align-top">
            @forelse ($entries as $entry)
                <tr class="hover:bg-canvas transition-colors duration-150">
                    <td class="{{ $td }} whitespace-nowrap text-muted-fg tabular-nums">
                        {{ $entry->created_at?->format('Y-m-d H:i:s') ?? '—' }}
                    </td>
                    <td class="{{ $td }}">
                        <div class="font-bold text-navy">{{ $entry->user_label ?? 'system' }}</div>
                        @if ($entry->user_role)
                            <div class="text-xs text-muted-fg">{{ $entry->user_role->label() }}</div>
                        @endif
                    </td>
                    <td class="{{ $td }}">
                        <span class="inline-block rounded-md bg-accent/10 text-accent px-2 py-0.5 font-mono text-xs">{{ $entry->action }}</span>
                        @if ($entry->description)
                            <div class="text-xs text-muted-fg mt-1">{{ $entry->description }}</div>
                        @endif
                    </td>
                    <td class="{{ $td }} whitespace-nowrap text-navy">{{ $entry->subjectLabel() }}</td>
                    <td class="{{ $td }}">
                        @php $changes = $entry->changes(); @endphp
                        @if (empty($changes))
                            <span class="text-muted-fg">—</span>
                        @else
                            <dl class="space-y-1 text-xs">
                                @foreach ($changes as $field => [$before, $after])
                                    <div class="flex gap-1 flex-wrap">
                                        <dt class="text-muted-fg">{{ $field }}:</dt>
                                        <dd class="text-danger line-through break-all">{{ \Illuminate\Support\Str::limit(json_encode($before), 60) }}</dd>
                                        <dd class="text-success break-all">{{ \Illuminate\Support\Str::limit(json_encode($after), 60) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </td>
                    <td class="{{ $td }} text-xs text-muted-fg whitespace-nowrap tabular-nums">{{ $entry->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-muted-fg">No audit entries match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</x-card>

<div class="mt-6">
    {{ $entries->links() }}
</div>
@endsection
