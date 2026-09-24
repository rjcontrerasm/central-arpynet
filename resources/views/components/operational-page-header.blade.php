@props([
    'active',
    'title',
    'subtitle' => null,
])

<div class="topbar">
    <a
        class="brand"
        href="{{ route('daily-ops.show') }}"
    >
        Central ARPYNET
    </a>

    <x-operational-nav :active="$active" />
</div>

<section class="hero">
    <div>
        <h1>{{ $title }}</h1>

        @if (filled($subtitle))
            <div class="subtitle">
                {{ $subtitle }}
            </div>
        @endif
    </div>

    @isset($actions)
        <div class="operational-header-actions">
            {{ $actions }}
        </div>
    @endisset
</section>
