<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Notificaciones · Central ARPYNET</title>

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

        a { color: inherit; text-decoration: none; }
        button { font: inherit; }

        .shell {
            width: min(100%, 860px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .notification-head {
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

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            color: #94a3b8;
            font-size: 13px;
        }

        .hero {
            align-items: end;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 7vw, 44px);
            line-height: 1;
            letter-spacing: -.05em;
        }

        .subtitle,
        .meta,
        .empty {
            color: #94a3b8;
        }

        .subtitle {
            margin-top: 7px;
            font-size: 13px;
        }

        .read-all {
            padding: 9px 12px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            color: #cbd5e1;
            cursor: pointer;
            font-size: 11px;
            font-weight: 800;
        }

        .success {
            margin-bottom: 12px;
            padding: 11px 13px;
            border: 1px solid #166534;
            border-radius: 12px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 12px;
        }

        .list {
            display: grid;
            gap: 9px;
        }

        .notification {
            display: block;
            padding: 14px;
            border: 1px solid #24304b;
            border-radius: 15px;
            background: #11182b;
        }

        .notification.unread {
            border-color: #3b82f6;
        }

        .title {
            font-weight: 800;
            line-height: 1.3;
        }

        .dot {
            width: 8px;
            height: 8px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: #3b82f6;
        }

        .meta {
            margin-top: 5px;
            font-size: 12px;
            line-height: 1.45;
        }

        .open {
            margin-top: 8px;
            color: #93c5fd;
            font-size: 11px;
            font-weight: 800;
        }

        .empty {
            padding: 24px;
            border: 1px dashed #334155;
            border-radius: 15px;
            text-align: center;
            font-size: 12px;
        }

        .pagination {
            margin-top: 18px;
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .nav,
            .subtitle,
            .meta,
            .empty {
                color: #64748b;
            }

            .notification,
            .read-all {
                background: #fff;
                border-color: #e2e8f0;
                color: #475569;
            }

            .notification.unread {
                border-color: #60a5fa;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <nav class="nav">
            <a href="{{ route('daily-ops.show') }}">
                Mi día
            </a>

            <a href="{{ route('executive-summary.show') }}">
                Resumen
            </a>

            <a href="{{ route('global-tracking.show') }}">
                Seguimiento
            </a>

            <a href="{{ route('notification-center.index') }}">
                Notificaciones
            </a>

            <a href="/historial">
                Historial
            </a>

            <a href="{{ url('/admin') }}">
                Panel →
            </a>
        </nav>
    </div>

    @if (session('notification_success'))
        <div class="success">
            {{ session('notification_success') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>Notificaciones</h1>

            <div class="subtitle">
                {{ $unreadCount }}
                {{ $unreadCount === 1
                    ? 'sin leer'
                    : 'sin leer' }}
            </div>
        </div>

        @if ($unreadCount > 0)
            <form
                method="POST"
                action="{{ route(
                    'notification-center.read-all'
                ) }}"
            >
                @csrf

                <button
                    class="read-all"
                    type="submit"
                >
                    Marcar todas leídas
                </button>
            </form>
        @endif
    </section>

    <div class="list">
        @forelse ($notifications as $notification)
            <form
                method="POST"
                action="{{ route(
                    'notification-center.read',
                    $notification->id,
                ) }}"
            >
                @csrf

                <button
                    type="submit"
                    class="notification {{
                        $notification->read_at
                            ? ''
                            : 'unread'
                    }}"
                    style="
                        width:100%;
                        color:inherit;
                        text-align:left;
                        cursor:pointer;
                    "
                >
                    <div class="notification-head">
                        <div class="title">
                            {{ $notification->data['title']
                                ?? 'Notificación' }}
                        </div>

                        @if (! $notification->read_at)
                            <span class="dot"></span>
                        @endif
                    </div>

                    <div class="meta">
                        {{ $notification->data['message']
                            ?? '' }}
                    </div>

                    <div class="meta">
                        {{ $notification->created_at
                            ->format('d/m/Y H:i') }}
                    </div>

                    <div class="open">
                        Abrir resumen →
                    </div>
                </button>
            </form>
        @empty
            <div class="empty">
                Aún no hay notificaciones.
            </div>
        @endforelse
    </div>

    <div class="pagination">
        {{ $notifications->links() }}
    </div>
</div>
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
</body>
</html>
