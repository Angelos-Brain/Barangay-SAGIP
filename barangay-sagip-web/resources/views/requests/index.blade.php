@extends('layouts.app')
@section('title', 'Requests — Barangay SAGIP')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-bold text-navy">
        {{ auth()->user()->isResident() ? 'My Requests' : 'All Requests' }}
    </h1>
    @if(auth()->user()->isResident())
        <a href="{{ route('requests.create') }}" class="bg-navy text-white text-sm rounded-md px-4 py-2 hover:bg-accent transition">
            + New Request
        </a>
    @endif
</div>

<div class="bg-surface rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-left">
            <tr>
                <th class="px-4 py-2">#</th>
                @unless(auth()->user()->isResident())
                    <th class="px-4 py-2">Resident</th>
                @endunless
                <th class="px-4 py-2">Category</th>
                <th class="px-4 py-2">Urgency</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Assigned To</th>
                <th class="px-4 py-2">Submitted</th>
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($requests as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-medium">#{{ $r->id }}</td>
                    @unless(auth()->user()->isResident())
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2"><x-avatar :user="$r->resident" size="h-7 w-7 text-xs" /><span>{{ $r->resident->name }}</span></div>
                        </td>
                    @endunless
                    <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $r->category ?? '—') }}</td>
                    <td class="px-4 py-2">
                        @if($r->urgency)
                            <x-badge :color="$r->urgency->badgeColor()">{{ $r->urgency->label() }}</x-badge>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <x-badge :color="$r->status->badgeColor()">{{ $r->status->label() }}</x-badge>
                        @if($r->needs_review)
                            <x-badge color="yellow">Needs Review</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $r->currentAssignment->responsePersonnel->name ?? '—' }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('requests.show', $r) }}" class="text-accent hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    @if ($requests->hasPages())
        <div class="border-t px-4 py-3">
            {{ $requests->links() }}
        </div>
    @endif
</div>
@endsection
