<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Colaboración · Central ARPYNET</title>

    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color-scheme: light dark;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #0b1020; color: #f8fafc; }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(100%, 1240px); margin: 0 auto; padding: 24px 16px 80px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:24px; }
        .brand { font-weight:850; letter-spacing:-.03em; }
        .hero { margin-bottom:24px; }
        h1 { margin:0; font-size:clamp(30px,7vw,46px); line-height:1; letter-spacing:-.05em; }
        .subtitle { max-width:760px; margin-top:8px; color:#94a3b8; font-size:13px; line-height:1.55; }
        .section-title { margin:28px 0 12px; font-size:13px; font-weight:850; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8; }
        .subjects { display:grid; grid-template-columns:repeat(auto-fit,minmax(245px,1fr)); gap:10px; }
        .subject, .comment { border:1px solid #24304b; border-radius:15px; background:#11182b; }
        .subject { padding:14px; transition:transform 120ms ease,border-color 120ms ease; }
        .subject:hover { transform:translateY(-1px); border-color:#3b82f6; }
        .badge { display:inline-flex; padding:4px 8px; border-radius:999px; background:#172554; color:#bfdbfe; font-size:10px; font-weight:850; text-transform:uppercase; letter-spacing:.04em; }
        .subject-title { margin-top:10px; font-size:14px; font-weight:820; line-height:1.35; }
        .meta { margin-top:6px; color:#94a3b8; font-size:11px; line-height:1.45; }
        .open { margin-top:12px; color:#93c5fd; font-size:11px; font-weight:850; }
        .feed { display:grid; gap:10px; }
        .comment { padding:15px; }
        .comment-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
        .author { font-weight:850; }
        .body { margin-top:9px; color:#e2e8f0; font-size:13px; line-height:1.55; white-space:pre-wrap; }
        .mentions { margin-top:8px; color:#93c5fd; font-size:11px; }
        .empty { padding:24px; border:1px dashed #334155; border-radius:15px; color:#94a3b8; text-align:center; font-size:12px; }
        .pagination { margin-top:18px; }
        @media (prefers-color-scheme: light) {
            body { background:#f8fafc; color:#0f172a; }
            .subtitle,.section-title,.meta,.empty { color:#64748b; }
            .subject,.comment { background:#fff; border-color:#e2e8f0; }
            .body { color:#334155; }
            .badge { background:#eff6ff; color:#1d4ed8; }
            .open,.mentions { color:#2563eb; }
        }
    </style>
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
