@props(['user' => null, 'name' => null, 'size' => 'h-8 w-8 text-xs'])
@php
    $name = $user?->name ?? $name ?? '?';
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $photoUrl = $user?->profilePhotoUrl();
@endphp
@if($photoUrl)
    <img src="{{ $photoUrl }}" alt="{{ $name }}" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover bg-gray-100"]) }}>
@else
    <span role="img" aria-label="{{ $name }}" {{ $attributes->merge(['class' => "$size shrink-0 inline-flex items-center justify-center rounded-full bg-navy/10 text-navy font-semibold"]) }}>{{ $initials ?: '?' }}</span>
@endif
