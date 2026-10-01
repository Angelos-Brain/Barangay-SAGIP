@extends('layouts.app')
@section('title', 'My Specializations — Barangay SAGIP')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-navy">My Specializations</h1>
    <p class="text-sm text-muted-fg mt-0.5 mb-6">
        {{ $personnel->name }} — currently tagged: <span class="font-bold text-navy">{{ $personnel->specializationLabels() }}</span>
    </p>

    <x-card>
        <form method="POST" action="{{ route('personnel.specializations.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-specialization-picker :specializations="$specializations"
                                     :selected="$personnel->specializationValues()"
                                     hint="You will be alerted about incidents matching these tags, and your request list is filtered to them." />

            <div class="flex items-center gap-3">
                <button class="min-h-11 rounded-xl bg-accent px-5 text-sm font-bold text-white hover:opacity-90 transition-opacity duration-200 cursor-pointer">
                    Save specializations
                </button>
                <a href="{{ route('dashboard') }}" class="min-h-11 inline-flex items-center px-3 text-sm font-bold text-muted-fg hover:underline">Cancel</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
