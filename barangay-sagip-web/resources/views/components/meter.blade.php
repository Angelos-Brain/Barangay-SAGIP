{{--
    Horizontal level bar with a live value (MASTER.md → Level / progress meter).
    Tone follows the occupancy thresholds unless one is passed explicitly.
--}}
@props(['label', 'value', 'max' => 100, 'display' => null, 'tone' => null])
@php
    $percent = $max > 0 ? (int) round(min(100, max(0, $value / $max * 100))) : 0;
    $tone ??= match (true) {
        $percent >= 100 => 'danger',
        $percent >= 80 => 'alert',
        $percent >= 50 => 'warning',
        default => 'success',
    };
    $fills = ['danger' => 'bg-danger', 'alert' => 'bg-alert', 'warning' => 'bg-warning', 'success' => 'bg-success', 'accent' => 'bg-accent'];
@endphp
<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        <span class="font-bold text-navy truncate">{{ $label }}</span>
        <span class="text-muted-fg tabular-nums shrink-0">{{ $display ?? $percent . '%' }}</span>
    </div>
    <div class="mt-1.5 h-2 rounded-full bg-muted overflow-hidden"
         role="meter" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}">
        <div class="h-full rounded-full {{ $fills[$tone] ?? $fills['accent'] }} transition-[width] duration-300" style="width: {{ $percent }}%"></div>
    </div>
</div>
