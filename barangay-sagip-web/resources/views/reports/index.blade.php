@extends('layouts.app')
@section('title', 'Reports — Barangay SAGIP')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-bold text-navy">Reports & Analytics</h1>
    <a href="{{ route('reports.export') }}" class="bg-navy text-white text-sm rounded-md px-4 py-2 hover:bg-accent transition">
        Export CSV
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div class="bg-surface rounded-lg shadow p-4">
        <h2 class="font-semibold text-navy mb-3 text-sm">Requests by Category</h2>
        <table class="w-full text-sm">
            @forelse ($byCategory as $category => $total)
                <tr class="border-b last:border-0">
                    <td class="py-1.5 capitalize">{{ str_replace('_', ' ', $category) }}</td>
                    <td class="py-1.5 text-right font-medium">{{ $total }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-gray-400">No data yet.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="bg-surface rounded-lg shadow p-4">
        <h2 class="font-semibold text-navy mb-3 text-sm">Requests by Urgency</h2>
        <table class="w-full text-sm">
            @forelse ($byUrgency as $urgency => $total)
                <tr class="border-b last:border-0">
                    <td class="py-1.5 capitalize">{{ $urgency }}</td>
                    <td class="py-1.5 text-right font-medium">{{ $total }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-gray-400">No data yet.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="bg-surface rounded-lg shadow p-4">
        <h2 class="font-semibold text-navy mb-3 text-sm">Average Response Time</h2>
        <p class="text-3xl font-bold text-accent">
            {{ $avgResponseMinutes ? round($avgResponseMinutes) . ' min' : '—' }}
        </p>
        <p class="text-xs text-gray-400 mt-1">From assignment to marked resolved.</p>
    </div>

    <div class="bg-surface rounded-lg shadow p-4">
        <h2 class="font-semibold text-navy mb-3 text-sm">Personnel Workload</h2>
        <table class="w-full text-sm">
            @forelse ($personnelWorkload as $p)
                <tr class="border-b last:border-0">
                    <td class="py-1.5">{{ $p->name }}</td>
                    <td class="py-1.5 text-right font-medium">{{ $p->total_assignments }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-gray-400">No data yet.</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection
