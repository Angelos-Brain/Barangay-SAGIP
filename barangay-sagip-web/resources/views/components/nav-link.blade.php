{{-- Sidebar navigation item. `active` is a route-name pattern (or list of patterns) for request()->routeIs(). --}}
@props(['href', 'icon', 'active' => null])
@php
    $isActive = $active !== null && request()->routeIs(...(array) $active);
@endphp
<a href="{{ $href }}"
   @if($isActive) aria-current="page" @endif
   {{ $attributes->merge(['class' => 'flex items-center gap-3 min-h-11 px-3 py-2.5 rounded-xl text-sm font-bold transition-colors duration-200 cursor-pointer '
        . ($isActive ? 'bg-accent text-white shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white')]) }}>
    <x-icon :name="$icon" />
    <span>{{ $slot }}</span>
</a>
