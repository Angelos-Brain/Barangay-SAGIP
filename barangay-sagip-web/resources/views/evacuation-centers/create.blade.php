@extends('layouts.app')
@section('title', 'Add Evacuation Center — Barangay SAGIP')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@section('content')
<div class="max-w-2xl mx-auto bg-surface rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl font-bold text-navy mb-4">Add Evacuation Center</h1>

    <x-evacuation-center-form :action="route('evacuation-centers.store')"
                              :statuses="$statuses"
                              submit-label="Save Center" />
</div>
@endsection
