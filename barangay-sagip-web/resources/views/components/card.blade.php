{{-- Standard content card (MASTER.md → Dashboard Adaptation → Cards). Pass padding="false" for edge-to-edge tables. --}}
@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'bg-surface rounded-2xl border border-line shadow-card overflow-hidden']) }}>
    @if($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 sm:px-6 pt-5 {{ $padding ? '' : 'pb-4 border-b border-line' }}">
            <div class="min-w-0">
                @if($title)<h2 class="text-base font-bold text-navy">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-sm text-muted-fg mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $padding ? 'p-5 sm:p-6' . ($title ? ' pt-4 sm:pt-4' : '') : '' }}">
        {{ $slot }}
    </div>
</section>
