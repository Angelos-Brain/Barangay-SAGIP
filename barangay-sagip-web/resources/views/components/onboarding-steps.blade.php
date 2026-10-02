{{--
    First Login progress: Email → Verify → Password → Ready.
    `current` is the 1-based step on screen; earlier steps show as done.
--}}
@props(['current'])

@php
    $steps = ['Email', 'Verify', 'Password', 'Ready'];
@endphp

<nav aria-label="Account setup progress" class="mb-6">
    <p class="sr-only">Step {{ $current }} of {{ count($steps) }}: {{ $steps[$current - 1] }}</p>
    <ol class="flex items-start" role="list">
        @foreach ($steps as $index => $label)
            @php
                $number = $index + 1;
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'upcoming');
            @endphp
            <li class="flex-1 flex flex-col items-center text-center relative"
                @if ($state === 'current') aria-current="step" @endif>
                @if (! $loop->first)
                    <span aria-hidden="true"
                          class="absolute top-4 right-1/2 w-full h-0.5 {{ $number <= $current ? 'bg-accent' : 'bg-line' }}"></span>
                @endif
                <span aria-hidden="true" @class([
                    'relative z-10 h-8 w-8 rounded-full inline-flex items-center justify-center text-sm font-bold border-2 transition-colors duration-200',
                    'bg-accent border-accent text-white' => $state !== 'upcoming',
                    'bg-surface border-line text-muted-fg' => $state === 'upcoming',
                ])>
                    @if ($state === 'done')
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'mt-1.5 text-[11px] sm:text-xs leading-tight',
                    'font-bold text-navy' => $state === 'current',
                    'text-muted-fg' => $state !== 'current',
                ])>
                    {{ $label }}<span class="sr-only">{{ $state === 'done' ? ' (completed)' : '' }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</nav>
