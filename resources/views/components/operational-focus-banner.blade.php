@props([
    'title',
    'meta' => null,
    'eyebrow' => 'Foco operativo',
    'tone' => 'info',
])

<section
    class="operational-focus-banner is-{{ $tone }}"
    aria-label="{{ $eyebrow }}"
>
    <div>
        <div class="operational-focus-eyebrow">
            {{ $eyebrow }}
        </div>

        <div class="operational-focus-title">
            {{ $title }}
        </div>

        @if (filled($meta))
            <div class="operational-focus-meta">
                {{ $meta }}
            </div>
        @endif
    </div>

    @isset($actions)
        <div class="operational-focus-actions">
            {{ $actions }}
        </div>
    @endisset
</section>
