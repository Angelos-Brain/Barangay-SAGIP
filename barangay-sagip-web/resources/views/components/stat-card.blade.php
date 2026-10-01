{{--
    Small metric card: icon tile + label + 1–2 metrics (MASTER.md → Stat cards).
    The value comes from `value`, or from the slot when it has to be live (e.g. x-text).
--}}
@props(['icon', 'label', 'value' => null, 'tone' => 'accent', 'hint' => null])
@php
    $tiles = [
        'accent' => 'bg-accent/10 text-accent',
        'danger' => 'bg-danger/10 text-danger',
        'alert' => 'bg-alert/10 text-alert',
        'warning' => 'bg-warning/10 text-warning',
        'success' => 'bg-success/10 text-success',
        'navy' => 'bg-navy/10 text-navy',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'bg-surface rounded-2xl border border-line shadow-card p-4 sm:p-5 flex items-start gap-3']) }}>
    <span class="h-10 w-10 rounded-xl inline-flex items-center justify-center shrink-0 {{ $tiles[$tone] ?? $tiles['accent'] }}">
        <x-icon :name="$icon" />
    </span>
    <div class="min-w-0">
        <p class="text-sm text-muted-fg leading-tight">{{ $label }}</p>
        <p class="text-2xl font-bold text-navy tabular-nums leading-tight mt-1">{{ $slot->isEmpty() ? $value : $slot }}</p>
        @if($hint)<p class="text-xs text-muted-fg mt-0.5">{{ $hint }}</p>@endif
    </div>
</div>
