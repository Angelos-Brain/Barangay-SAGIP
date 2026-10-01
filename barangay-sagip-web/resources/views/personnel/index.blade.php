@extends('layouts.app')
@section('title', 'Response Personnel — Barangay SAGIP')

@php
    $th = 'px-5 py-3 text-xs font-bold uppercase tracking-wide';
    $td = 'px-5 py-3';
    $availableCount = $personnel->where('is_available', true)->count();
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-navy">Response Personnel</h1>
        <p class="text-sm text-muted-fg mt-0.5">Responders, their specialization tags and live availability.</p>
    </div>
    <a href="{{ route('personnel.create') }}" class="inline-flex items-center gap-2 min-h-11 rounded-xl bg-accent px-5 py-2.5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200">
        <x-icon name="plus" /> Add Personnel
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6">
    <x-stat-card icon="users" label="Registered responders" :value="$personnel->count()" tone="navy" />
    <x-stat-card icon="user-check" label="Available now" :value="$availableCount" tone="success"
                 :hint="$personnel->count() ? round($availableCount / $personnel->count() * 100) . '% of the roster' : null" />
    <x-stat-card icon="clipboard" label="Active assignments" :value="$personnel->sum('active_assignments_count')" tone="accent" />
</div>

<x-card title="Roster" :padding="false">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-canvas text-muted-fg text-left">
            <tr>
                <th class="{{ $th }}">Name</th>
                <th class="{{ $th }}">Specialization</th>
                <th class="{{ $th }}">Active Assignments</th>
                <th class="{{ $th }}">Status</th>
                <th class="{{ $th }}">Last Location Update</th>
                <th class="{{ $th }}"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($personnel as $p)
                <tr class="hover:bg-canvas transition-colors duration-150 align-top">
                    <td class="{{ $td }}">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$p->user" :name="$p->name" size="h-9 w-9 text-xs" />
                            <div>
                                <p class="font-bold text-navy">{{ $p->name }}</p>
                                @if($p->user?->role)
                                    <p class="text-xs text-muted-fg">{{ $p->user->role->label() }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="{{ $td }}">
                        <div class="flex flex-wrap gap-1.5 max-w-xs">
                            @foreach ($p->specializationEnums() as $specialization)
                                <x-badge :color="$specialization->badgeColor()">{{ $specialization->label() }}</x-badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="{{ $td }} tabular-nums font-bold text-navy">{{ $p->active_assignments_count }}</td>
                    <td class="{{ $td }}">
                        <x-badge :color="$p->is_available ? 'green' : 'gray'">
                            {{ $p->is_available ? 'Available' : 'Unavailable' }}
                        </x-badge>
                        @if(! $p->is_available && $p->unavailability_reason)
                            <p class="text-xs text-muted-fg mt-1 max-w-xs">{{ $p->unavailability_reason }}</p>
                        @endif
                    </td>
                    <td class="{{ $td }} text-muted-fg whitespace-nowrap">
                        {{ $p->last_location_update?->diffForHumans() ?? '—' }}
                    </td>
                    <td class="{{ $td }}">
                        <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                            <a href="{{ route('personnel.edit', $p) }}" class="inline-flex items-center min-h-9 rounded-lg px-3 text-sm font-bold text-accent hover:bg-accent/10 transition-colors duration-200">Edit</a>
                            <form method="POST" action="{{ route('personnel.toggleAvailability', $p) }}" class="inline">
                                @csrf
                                <button class="inline-flex items-center min-h-9 rounded-lg px-3 text-sm font-bold text-accent hover:bg-accent/10 transition-colors duration-200 cursor-pointer">
                                    Mark {{ $p->is_available ? 'Unavailable' : 'Available' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('personnel.destroy', $p) }}"
                                  onsubmit="return confirm('Remove {{ $p->name }}? This can\'t be undone.');"
                                  class="inline">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center min-h-9 rounded-lg px-3 text-sm font-bold text-danger hover:bg-danger/10 transition-colors duration-200 cursor-pointer">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-muted-fg">No personnel registered yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</x-card>
@endsection
