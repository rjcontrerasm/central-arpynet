<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Colaboración · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/collaboration-index.css') }}?v={{ filemtime(public_path('central-assets/pages/collaboration-index.css')) }}"
    >
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="collaboration" />
    </div>

    <section class="hero">
        <h1>Colaboración</h1>
        <div class="subtitle">
            Conversaciones operativas dentro de tareas, proyectos, servicios e incidentes.
            Las menciones generan una notificación interna y respetan el ámbito de cada empresa.
        </div>
    </section>

    <div class="section-title">Abrir una conversación</div>

    <div class="subjects">
        @forelse ($subjects as $subject)
            <a
                class="subject"
                href="{{ route('collaboration.thread', [
                    'type' => $subject['type'],
                    'id' => $subject['id'],
                ]) }}"
            >
                <span class="badge">{{ $subject['label'] }}</span>
                <div class="subject-title">{{ $subject['title'] }}</div>
                <div class="meta">{{ $subject['organization_name'] }}</div>
                <div class="open">Abrir conversación →</div>
            </a>
        @empty
            <div class="empty">
                No hay elementos disponibles para tu ámbito actual.
            </div>
        @endforelse
    </div>

    <div class="section-title">Actividad reciente</div>

    <div class="feed">
        @forelse ($comments as $comment)
            @php
                $commentable = $comment->commentable;
                $resolver = app(\App\Support\CollaborationSubjectResolver::class);
            @endphp

            @if ($commentable)
                <a
                    class="comment"
                    href="{{ route('collaboration.thread', [
                        'type' => $resolver->typeFor($commentable),
                        'id' => $commentable->getKey(),
                    ]) }}"
                >
                    <div class="comment-head">
                        <div>
                            <div class="author">{{ $comment->author?->name ?? 'Usuario' }}</div>
                            <div class="meta">
                                {{ $resolver->label($commentable) }} ·
                                {{ $resolver->title($commentable) }} ·
                                {{ $comment->organization?->name ?? 'Empresa' }}
                            </div>
                        </div>
                        <div class="meta">{{ $comment->created_at->format('d/m H:i') }}</div>
                    </div>

                    <div class="body">{{ $comment->body }}</div>

                    @if ($comment->mentions->isNotEmpty())
                        <div class="mentions">
                            Menciones:
                            {{ $comment->mentions->pluck('name')->join(', ') }}
                        </div>
                    @endif
                </a>
            @endif
        @empty
            <div class="empty">
                Aún no hay conversaciones. Abre un elemento para publicar el primer comentario.
            </div>
        @endforelse
    </div>

    <div class="pagination">{{ $comments->links() }}</div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
