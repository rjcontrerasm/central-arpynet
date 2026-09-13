<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $subjectTitle }} · Colaboración · Central ARPYNET</title>

    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color-scheme: light dark;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:#0b1020; color:#f8fafc; }
        a { color:inherit; text-decoration:none; }
        button, textarea, select { font:inherit; }
        .shell { width:min(100%,980px); margin:0 auto; padding:24px 16px 80px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:24px; }
        .brand { font-weight:850; letter-spacing:-.03em; }
        .back { display:inline-flex; margin-bottom:14px; color:#93c5fd; font-size:12px; font-weight:800; }
        .hero { padding:18px; border:1px solid #24304b; border-radius:17px; background:#11182b; }
        .badge { display:inline-flex; padding:4px 8px; border-radius:999px; background:#172554; color:#bfdbfe; font-size:10px; font-weight:850; text-transform:uppercase; letter-spacing:.04em; }
        h1 { margin:10px 0 0; font-size:clamp(26px,6vw,38px); line-height:1.08; letter-spacing:-.045em; }
        .meta { margin-top:6px; color:#94a3b8; font-size:11px; line-height:1.45; }
        .success, .errors, .readonly { margin-top:12px; padding:11px 13px; border-radius:12px; font-size:12px; }
        .success { border:1px solid #166534; background:#052e16; color:#bbf7d0; }
        .errors { border:1px solid #991b1b; background:#450a0a; color:#fecaca; }
        .readonly { border:1px solid #475569; background:#0f172a; color:#cbd5e1; }
        .composer, .thread { margin-top:18px; }
        .composer { padding:16px; border:1px solid #24304b; border-radius:16px; background:#11182b; }
        label { display:block; margin-bottom:6px; font-size:11px; font-weight:850; color:#cbd5e1; }
        textarea, select { width:100%; border:1px solid #334155; border-radius:11px; background:#0f172a; color:#f8fafc; }
        textarea { min-height:120px; padding:11px 12px; resize:vertical; line-height:1.5; }
        select { min-height:118px; padding:7px; }
        .field + .field { margin-top:12px; }
        .hint { margin-top:5px; color:#94a3b8; font-size:10px; line-height:1.45; }
        .submit { margin-top:12px; padding:10px 14px; border:1px solid #2563eb; border-radius:10px; background:#1d4ed8; color:#fff; font-weight:850; cursor:pointer; }
        .thread { display:grid; gap:10px; }
        .comment { padding:14px; border:1px solid #24304b; border-radius:15px; background:#11182b; }
        .comment-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
        .author { font-size:13px; font-weight:850; }
        .body { margin-top:9px; color:#e2e8f0; font-size:13px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
        .mentions { margin-top:8px; color:#93c5fd; font-size:11px; }
        .empty { padding:24px; border:1px dashed #334155; border-radius:15px; color:#94a3b8; text-align:center; font-size:12px; }
        @media (prefers-color-scheme: light) {
            body { background:#f8fafc; color:#0f172a; }
            .hero,.composer,.comment { background:#fff; border-color:#e2e8f0; }
            .badge { background:#eff6ff; color:#1d4ed8; }
            .meta,.hint,.empty { color:#64748b; }
            label,.body { color:#334155; }
            textarea,select { background:#fff; color:#0f172a; border-color:#cbd5e1; }
            .readonly { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
            .back,.mentions { color:#2563eb; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="collaboration" />
    </div>

    <a class="back" href="{{ route('collaboration.index') }}">← Volver a colaboración</a>

    <section class="hero">
        <span class="badge">{{ $subjectLabel }}</span>
        <h1>{{ $subjectTitle }}</h1>
        <div class="meta">
            {{ $subject->organization?->name ?? 'Empresa' }} ·
            conversación operativa #{{ $subject->getKey() }}
        </div>
    </section>

    @if (session('collaboration_success'))
        <div class="success">{{ session('collaboration_success') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($canWrite)
        <form
            class="composer"
            method="POST"
            action="{{ route('collaboration.store', [
                'type' => $type,
                'id' => $subject->getKey(),
            ]) }}"
        >
            @csrf

            <div class="field">
                <label for="body">Comentario</label>
                <textarea
                    id="body"
                    name="body"
                    maxlength="5000"
                    required
                    placeholder="Escribe contexto, avance, bloqueo o decisión…"
                >{{ old('body') }}</textarea>
            </div>

            <div class="field">
                <label for="mentions">Mencionar personas</label>
                <select id="mentions" name="mentions[]" multiple>
                    @foreach ($members as $member)
                        <option
                            value="{{ $member->id }}"
                            @selected(in_array(
                                $member->id,
                                old('mentions', []),
                            ))
                        >
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
                <div class="hint">
                    Opcional. Usa Ctrl/Cmd para seleccionar varias personas. Las menciones generan una notificación interna.
                </div>
            </div>

            <button
                class="submit"
                type="submit"
                data-busy-label="Publicando…"
            >
                Publicar comentario
            </button>
        </form>
    @else
        <div class="readonly">
            Tienes acceso de solo lectura en esta empresa. Puedes consultar la conversación, pero no publicar comentarios.
        </div>
    @endif

    <div class="thread">
        @forelse ($comments as $comment)
            <article class="comment" id="comentario-{{ $comment->id }}">
                <div class="comment-head">
                    <div class="author">{{ $comment->author?->name ?? 'Usuario' }}</div>
                    <div class="meta">{{ $comment->created_at->format('d/m/Y H:i') }}</div>
                </div>

                <div class="body">{{ $comment->body }}</div>

                @if ($comment->mentions->isNotEmpty())
                    <div class="mentions">
                        Menciones: {{ $comment->mentions->pluck('name')->join(', ') }}
                    </div>
                @endif
            </article>
        @empty
            <div class="empty">
                Aún no hay comentarios. Esta conversación está lista para comenzar.
            </div>
        @endforelse
    </div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
