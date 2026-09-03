<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Revisión diaria · Central ARPYNET</title>

    <style>
        :root {
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color-scheme: light dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #0b1020;
            color: #f8fafc;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button { font: inherit; }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .review-head,
        .progress-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar { margin-bottom: 24px; }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .hero {
            align-items: end;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(31px, 7vw, 46px);
            line-height: .98;
            letter-spacing: -.05em;
        }

        h2 {
            margin: 0;
            font-size: 17px;
        }

        .subtitle,
        .meta {
            color: #94a3b8;
        }

        .subtitle {
            margin-top: 7px;
            font-size: 13px;
        }

        .progress-card {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #24304b;
            border-radius: 16px;
            background: #11182b;
        }

        .progress-head {
            align-items: baseline;
        }

        .progress-title {
            font-size: 14px;
            font-weight: 820;
        }

        .progress-value {
            font-size: 12px;
            color: #93c5fd;
            font-weight: 800;
        }

        .progress-track {
            height: 8px;
            margin-top: 11px;
            overflow: hidden;
            border-radius: 999px;
            background: #1e293b;
        }

        .progress-bar {
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }

        .complete-note {
            margin-top: 10px;
            color: #86efac;
            font-size: 12px;
            font-weight: 750;
        }

        .success {
            margin-bottom: 14px;
            padding: 11px 13px;
            border: 1px solid #166534;
            border-radius: 12px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 12px;
            font-weight: 750;
        }

        .review-grid {
            display: grid;
            gap: 10px;
        }

        .review-card {
            align-self: start;
            padding: 15px;
            border: 1px solid #24304b;
            border-radius: 16px;
            background: #11182b;
        }

        .review-card.reviewed {
            border-color: #166534;
        }

        .review-head {
            align-items: start;
        }

        .count {
            min-width: 34px;
            padding: 5px 8px;
            border-radius: 999px;
            background: #1e293b;
            color: #cbd5e1;
            text-align: center;
            font-size: 11px;
            font-weight: 850;
        }

        .reviewed .count {
            background: #052e16;
            color: #bbf7d0;
        }

        .description {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.45;
        }

        .links,
        .review-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }

        .link {
            display: inline-flex;
            min-height: 37px;
            align-items: center;
            padding: 7px 10px;
            border: 1px solid #334155;
            border-radius: 9px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 800;
        }

        .review-button {
            min-height: 37px;
            padding: 7px 11px;
            border: 0;
            border-radius: 9px;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            font-weight: 850;
            cursor: pointer;
        }

        .reviewed-label {
            display: inline-flex;
            min-height: 37px;
            align-items: center;
            color: #86efac;
            font-size: 11px;
            font-weight: 850;
        }

        @media (min-width: 760px) {
            .review-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .progress-card,
            .review-card {
                background: #fff;
                border-color: #e2e8f0;
            }

            .review-card.reviewed {
                border-color: #86efac;
            }

            .progress-track {
                background: #e2e8f0;
            }

            .count {
                background: #f1f5f9;
                color: #475569;
            }

            .reviewed .count {
                background: #f0fdf4;
                color: #166534;
            }

            .link {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }
        }
    </style>
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
                class="progress-bar"
                style="width: {{ $reviewedCount * 25 }}%"
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
