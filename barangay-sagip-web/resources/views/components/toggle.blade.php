{{--
    Labeled switch backed by a real checkbox (MASTER.md → Toggle switch).
    Extra attributes (id, name, x-model, checked…) land on the <input>.
--}}
@props(['label', 'dot' => null, 'hint' => null])
<label class="flex items-center justify-between gap-3 min-h-11 cursor-pointer select-none">
    <span class="flex items-center gap-2.5 min-w-0">
        @if($dot)<span class="h-3 w-3 rounded-full ring-2 ring-surface shadow shrink-0" style="background: {{ $dot }}"></span>@endif
        <span class="min-w-0">
            <span class="block text-sm font-bold text-navy">{{ $label }}</span>
            @if($hint)<span class="block text-xs text-muted-fg">{{ $hint }}</span>@endif
        </span>
    </span>
    <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span aria-hidden="true"
          class="relative h-6 w-11 shrink-0 rounded-full bg-muted transition-colors duration-200 peer-checked:bg-accent
                 peer-focus-visible:ring-[3px] peer-focus-visible:ring-accent/40
                 after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow
                 after:transition-transform after:duration-200 peer-checked:after:translate-x-5"></span>
</label>
