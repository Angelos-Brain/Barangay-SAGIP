@props([
    'specializations' => [],
    'selected' => [],
    'label' => 'Specializations',
    'hint' => 'Pick every kind of emergency this responder handles. Tags decide which incidents they are alerted about.',
])
@php
    $selected = collect(old('specializations', $selected))->map(fn ($value) => (string) $value)->all();
@endphp
<fieldset>
    <legend class="block text-sm font-bold text-navy mb-1">{{ $label }}</legend>
    @if ($hint)
        <p class="text-sm text-muted-fg mb-3">{{ $hint }}</p>
    @endif
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 rounded-2xl border border-line px-4 divide-y sm:divide-y-0 divide-line">
        @foreach ($specializations as $value => $text)
            <x-toggle :label="$text" name="specializations[]" :value="$value"
                      :checked="in_array((string) $value, $selected, true)" />
        @endforeach
    </div>
    @error('specializations')
        <p class="text-sm text-danger mt-1.5">{{ $message }}</p>
    @enderror
</fieldset>
