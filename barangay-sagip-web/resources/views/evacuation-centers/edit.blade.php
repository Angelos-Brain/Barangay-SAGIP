@extends('layouts.app')
@section('title', 'Edit Evacuation Center — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@section('content')
<div class="max-w-2xl mx-auto bg-surface rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl font-bold text-navy mb-1">Edit {{ $center->name }}</h1>
    <p class="text-xs text-gray-500 mb-4">
        {{ number_format($center->current_occupancy) }} of {{ number_format($center->capacity) }} spaces used
        ({{ $center->occupancyPercentage() }}%).
    </p>

    <x-evacuation-center-form :center="$center"
                              :action="route('evacuation-centers.update', $center)"
                              method="PUT"
                              :statuses="$statuses"
                              submit-label="Update Center" />
</div>
@endsection
