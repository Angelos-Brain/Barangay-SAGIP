@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => true,
    'placeholder' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-bold text-navy mb-1.5">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        @if($required) required @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->merge([
            'class' => 'w-full min-h-12 rounded-xl bg-surface border border-line px-4 py-3 text-base text-ink placeholder-slate-400 '
                     . 'focus:outline-none focus:ring-[3px] focus:ring-navy/10 focus:border-navy transition-colors duration-200'
        ]) }}
    >
    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
