<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Revisión diaria · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/daily-review.css') }}?v=2.39.2"
    >
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="review" />
    </div>

    @if (session('daily_review_success'))
        <div class="success">
            {{ session('daily_review_success') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>Revisión diaria</h1>

            <div class="subtitle">
                {{ $now->locale('es')->translatedFormat(
                    'l d \d\e F',
                ) }}
                · confirma cada bloque después de revisarlo
            </div>
        </div>
    </section>

    <section class="progress-card">
        <div class="progress-head">
            <div class="progress-title">
                Progreso de hoy
            </div>

            <div class="progress-value">
                {{ $reviewedCount }}/4 revisados
            </div>
        </div>

        <div class="progress-track">
            <div
                class="progress-bar progress-{{ $reviewedCount * 25 }}"
            ></div>
        </div>

        @if ($review?->completed_at)
            <div class="complete-note">
                ✓ Revisión completada a las
                {{ $review->completed_at->format('H:i') }}
            </div>
        @endif
    </section>

    <div class="review-grid">
        @foreach ($steps as $key => $step)
            <article
                class="review-card {{
                    $step['reviewed']
                        ? 'reviewed'
                        : ''
                }}"
                data-operational-card
            >
                <div class="review-head">
                    <div>
                        <h2>{{ $step['title'] }}</h2>

                        <div class="description">
                            {{ $step['description'] }}
                        </div>
                    </div>

                    <div class="count">
                        {{ $step['count'] }}
                    </div>
                </div>

                <div class="links">
                    @foreach ($step['links'] as $link)
                        <a
                            class="link"
                            href="{{ $link['url'] }}"
                        >
                            {{ $link['label'] }} →
                        </a>
                    @endforeach
                </div>

                <div class="review-actions">
                    @if ($step['reviewed'])
                        <span class="reviewed-label">
                            ✓ Revisado
                        </span>
                    @else
                        <form
                            method="POST"
                            action="{{ route(
                                'daily-review.mark',
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="step"
                                value="{{ $key }}"
                            >

                            <button
                                class="review-button"
                                type="submit"
                                data-busy-label="Guardando…"
                            >
                                Marcar revisado
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
