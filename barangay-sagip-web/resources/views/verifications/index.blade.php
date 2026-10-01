@extends('layouts.app')
@section('title', 'Account Verification — Barangay SAGIP')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h1 class="text-xl font-bold text-navy">Account Verification</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            Residents cannot file emergency reports until their account is verified.
        </p>
    </div>
</div>

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    @foreach (\App\Enums\VerificationStatus::cases() as $case)
        <a href="{{ route('verifications.index', ['status' => $case->value]) }}"
           class="rounded-md px-3 py-1.5 border {{ $status === $case ? 'bg-navy text-white border-navy' : 'bg-surface text-gray-600 border-gray-200 hover:bg-gray-50' }}">
            {{ $case->label() }}
            <span class="ml-1 text-xs opacity-75">{{ $counts[$case->value] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<div class="bg-surface rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-left">
            <tr>
                <th class="px-4 py-2">Resident</th>
                <th class="px-4 py-2">Contact</th>
                <th class="px-4 py-2">Address</th>
                <th class="px-4 py-2">Vulnerability</th>
                <th class="px-4 py-2">Registered</th>
                <th class="px-4 py-2 text-right">Decision</th>
            </tr>
        </thead>
        <tbody class="divide-y align-top">
            @forelse ($accounts as $account)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <x-avatar :user="$account" size="h-8 w-8 text-xs" />
                            <div>
                                <div class="font-medium">{{ $account->name }}</div>
                                <div class="text-xs text-gray-400">{{ $account->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 text-gray-600">{{ $account->phone_number ?? '—' }}</td>
                    <td class="px-4 py-2 text-gray-600 max-w-xs">{{ $account->residentProfile?->address ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap gap-1">
                            @forelse ($account->residentProfile?->vulnerabilityTagEnums() ?? [] as $tag)
                                <x-badge :color="$tag->badgeColor()">{{ $tag->shortLabel() }}</x-badge>
                            @empty
                                <span class="text-gray-400">Not declared</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $account->created_at?->diffForHumans() }}</td>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('verifications.update', $account) }}"
                              class="flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-end">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="note" maxlength="500" placeholder="Note (optional)"
                                   class="rounded-md border-gray-300 text-xs px-2 py-1 w-full sm:w-40">
                            @if ($status !== \App\Enums\VerificationStatus::Verified)
                                <button name="decision" value="verified"
                                        class="bg-green-600 text-white text-xs rounded-md px-3 py-1.5 hover:bg-green-700 transition whitespace-nowrap">
                                    Verify
                                </button>
                            @endif
                            @if ($status !== \App\Enums\VerificationStatus::Rejected)
                                <button name="decision" value="rejected"
                                        class="bg-red-600 text-white text-xs rounded-md px-3 py-1.5 hover:bg-red-700 transition whitespace-nowrap">
                                    Reject
                                </button>
                            @endif
                            @if ($status !== \App\Enums\VerificationStatus::Pending)
                                <button name="decision" value="pending"
                                        class="bg-gray-200 text-gray-700 text-xs rounded-md px-3 py-1.5 hover:bg-gray-300 transition whitespace-nowrap">
                                    Re-open
                                </button>
                            @endif
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No {{ strtolower($status->label()) }} accounts.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-4">{{ $accounts->links() }}</div>
@endsection
