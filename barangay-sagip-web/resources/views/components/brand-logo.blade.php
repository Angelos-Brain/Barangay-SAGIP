{{--
    The official Barangay SAGIP logo, used as-is. Serves public/images/sagip-logo.svg when it is
    present and falls back to the existing PNG until then.
--}}
@php
    $logoPath = file_exists(public_path('images/sagip-logo.svg')) ? 'images/sagip-logo.svg' : 'images/sagip-logo.png';
@endphp
<img src="{{ asset($logoPath) }}" alt="Barangay SAGIP" {{ $attributes->merge(['class' => 'w-auto']) }}>
