@once
<link
    rel="stylesheet"
    href="{{ asset('central-assets/operational.css') }}?v={{ filemtime(public_path('central-assets/operational.css')) }}"
>
<script
    src="{{ asset('central-assets/operational.js') }}?v={{ filemtime(public_path('central-assets/operational.js')) }}"
    defer
></script>
@endonce
