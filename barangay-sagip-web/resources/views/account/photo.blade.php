@extends('layouts.app')
@section('title', 'My Photo — Barangay SAGIP')

@section('content')
<div class="max-w-lg mx-auto bg-surface rounded-lg shadow p-4 sm:p-6">
    <h1 class="text-xl font-bold text-navy mb-1">My Photo</h1>
    <p class="text-sm text-gray-500 mb-6">
        Your photo appears next to your name on requests, assignments, and staff lists.
    </p>

    <x-photo-upload :user="$user" :action="route('account.photo.update')" />
</div>
@endsection
