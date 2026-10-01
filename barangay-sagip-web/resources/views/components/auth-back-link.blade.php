{{--
    Back navigation for the signed-out personnel screens, which sit outside
    layouts/app.blade.php and so don't get <x-back-link>. Pass `action` to make
    it a POST (e.g. signing out of a half-finished setup) instead of a link.
--}}
@props(['href' => null, 'action' => null, 'label' => 'Back'])

@php
    $classes = 'min-h-11 -ml-1 px-1 inline-flex items-center gap-1.5 text-sm font-bold text-muted-fg hover:text-accent '
             .'rounded-lg transition-colors duration-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-accent';
@endphp

<div class="mb-4">
    @if ($action)
        <form method="POST" action="{{ $action }}">
            @csrf
            <button type="submit" class="{{ $classes }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ $label }}
            </button>
        </form>
    @else
        <a href="{{ $href }}" class="{{ $classes }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ $label }}
        </a>
    @endif
</div>
