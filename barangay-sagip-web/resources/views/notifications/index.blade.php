@extends('layouts.app')
@section('title', 'Notifications — Barangay SAGIP')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-xl font-bold text-navy">Notifications</h1>
    <form method="POST" action="{{ route('notifications.readAll') }}">
        @csrf
        <button class="py-2 sm:py-0 text-sm text-accent hover:underline">Mark all as read</button>
    </form>
</div>

<div class="bg-surface rounded-lg shadow divide-y">
    @forelse ($notifications as $n)
        <div class="p-4 flex items-start justify-between gap-3 {{ $n->read_at ? 'opacity-60' : '' }}">
            <div class="min-w-0">
                @php
                    $link = $n->data['url']
                        ?? (isset($n->data['emergency_request_id']) ? route('requests.show', $n->data['emergency_request_id']) : null);
                @endphp
                @if ($link)
                    <a href="{{ $link }}" class="text-sm break-words hover:text-accent hover:underline">{{ $n->data['message'] ?? 'Notification' }}</a>
                @else
                    <p class="text-sm break-words">{{ $n->data['message'] ?? 'Notification' }}</p>
                @endif
                <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                @unless($n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                        @csrf
                        <button class="py-2 -my-2 sm:py-0 sm:my-0 text-xs text-accent hover:underline whitespace-nowrap">Mark read</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('notifications.destroy', $n->id) }}"
                      onsubmit="return confirm('Delete this notification?');">
                    @csrf
                    @method('DELETE')
                    <button class="py-2 -my-2 sm:py-0 sm:my-0 text-xs text-red-600 hover:underline whitespace-nowrap">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <p class="p-6 text-center text-gray-400 text-sm">No notifications yet.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $notifications->links() }}
</div>
@endsection
