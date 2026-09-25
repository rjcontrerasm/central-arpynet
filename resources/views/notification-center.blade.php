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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/notification-center.css') }}?v=2.39.2"
    >
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="notifications" />
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
                    }} notification-trigger"
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
<script src="{{ asset('central-assets/pages/notification-center.js') }}?v=2.39.2"></script>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
