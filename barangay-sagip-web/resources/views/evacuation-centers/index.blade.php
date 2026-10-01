@extends('layouts.app')
@section('title', 'Evacuation Centers — Barangay SAGIP')

@section('content')
@php $canManage = auth()->user()->can(\App\Enums\Permission::EvacuationCentersManage->value); @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h1 class="text-xl font-bold text-navy">Evacuation Centers</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            {{ $totals['accepting'] }} of {{ $totals['centers'] }} currently accepting ·
            {{ number_format($totals['occupancy']) }} / {{ number_format($totals['capacity']) }} spaces used
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('map.index') }}" class="text-sm text-accent hover:underline">View on map</a>
        @if($canManage)
            <a href="{{ route('evacuation-centers.create') }}"
               class="bg-navy text-white text-sm rounded-md px-4 py-2 hover:bg-accent transition">
                + Add Center
            </a>
        @endif
    </div>
</div>

<div class="bg-surface rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-left">
            <tr>
                <th class="px-4 py-2">Center</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Occupancy</th>
                <th class="px-4 py-2">Space Left</th>
                <th class="px-4 py-2">Contact</th>
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($centers as $center)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2">
                        <div class="font-medium">{{ $center->name }}</div>
                        @if($center->address)
                            <div class="text-xs text-gray-400">{{ $center->address }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <x-badge :color="$center->status->badgeColor()">{{ $center->status->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full
                                            {{ match($center->occupancyBadgeColor()) {
                                                'red' => 'bg-red-500',
                                                'orange' => 'bg-orange-500',
                                                'yellow' => 'bg-yellow-500',
                                                default => 'bg-green-500',
                                            } }}"
                                     style="width: {{ $center->occupancyPercentage() }}%"></div>
                            </div>
                            <span class="text-xs text-gray-600 whitespace-nowrap">
                                {{ number_format($center->current_occupancy) }} / {{ number_format($center->capacity) }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-2 font-medium">
                        @if($center->canAcceptEvacuees())
                            {{ number_format($center->remainingCapacity()) }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-gray-600">
                        {{ $center->contact_person ?? '—' }}
                        @if($center->contact_number)
                            <div class="text-xs text-gray-400">{{ $center->contact_number }}</div>
                        @endif
                    </td>
                    @if($canManage)
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('evacuation-centers.edit', $center) }}" class="text-accent hover:underline">Edit</a>
                            <form method="POST" action="{{ route('evacuation-centers.destroy', $center) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline ml-2"
                                        onclick="return confirm('Remove {{ $center->name }} from the roster?')">
                                    Remove
                                </button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-400">
                        No evacuation centers on the roster yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
