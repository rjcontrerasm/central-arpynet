<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>CENTRAL Copilot · Central ARPYNET</title>
<x-operational-theme />
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/central-copilot.css') }}?v=2.39.2"
    >
</head>
<body>
<div class="copilot">
<header class="topbar">
<a class="brand" href="{{ route('daily-ops.show') }}">Central ARPYNET</a>
<x-operational-nav active="copilot" />
</header>

<section class="hero">
<div>
<h1>CENTRAL Copilot</h1>
<div class="subtitle">Pregunta por tu jornada, riesgos, panorama, decisiones o cierre diario. Copilot responde solo con contexto verificable que ya existe en CENTRAL.</div>
</div>
</section>

<form class="ask shell-card" method="get" action="{{ route('central-copilot.index') }}">
<div class="ask-grid">
<input name="q" value="{{ $query }}" maxlength="500" autocomplete="off" placeholder="Ej. ¿Qué hago hoy?" aria-label="Pregunta para CENTRAL Copilot">
<select name="scope" aria-label="Ámbito de Copilot">
<option value="">Ámbito actual / automático</option>
@foreach($organizations as $organization)
<option value="{{ $organization->id }}" @selected($selectedScope === (int)$organization->id)>{{ $organization->name }}</option>
@endforeach
</select>
<button type="submit" data-busy-label="Consultando…">Preguntar</button>
</div>
<div class="ask-note">Consulta de solo lectura · sin red externa · sin escritura · sin ejecución autónoma provocada por la pregunta.</div>
</form>

<div class="suggestions" aria-label="Preguntas sugeridas">
@foreach($answer['suggestions'] as $suggestion)
<a class="suggestion" href="{{ route('central-copilot.index', array_filter(['scope'=>$selectedScope,'q'=>$suggestion])) }}">{{ $suggestion }}</a>
@endforeach
</div>

<section class="answer shell-card" aria-live="polite">
<div class="answer-head">
<div>
<h2>{{ $answer['title'] }}</h2>
@if($focusOrganization)
<div class="muted">Foco: {{ $focusOrganization->name }}</div>
@endif
</div>
<div class="answer-badges">
<span class="badge">{{ $answer['intent'] }}</span>
<span class="badge safe">Solo lectura</span>
</div>
</div>

<div class="answer-copy">{{ $answer['answer'] }}</div>

@if(!empty($answer['items']))
<div class="items">
@foreach($answer['items'] as $item)
<div class="item">
@if(!empty($item['url']))
<a class="item-title" href="{{ $item['url'] }}">{{ $item['title'] }} →</a>
@else
<div class="item-title">{{ $item['title'] }}</div>
@endif
@if(!empty($item['meta']))<div class="item-meta">{{ $item['meta'] }}</div>@endif
</div>
@endforeach
</div>
@endif

<div class="answer-actions">
@if(!empty($answer['cta']['route']))
<a class="action-link" href="{{ route($answer['cta']['route']) }}">{{ $answer['cta']['label'] }} →</a>
@endif
<a class="action-link" href="{{ route('agent-proposals.index', array_filter(['scope'=>$selectedScope])) }}">Centro de propuestas →</a>
</div>
</section>

<section class="metrics" aria-label="Estado de propuestas">
<div class="metric"><strong>{{ $proposalSummary['pending'] }}</strong><span>PROPUESTAS PENDIENTES</span></div>
<div class="metric"><strong>{{ $proposalSummary['approved'] }}</strong><span>APROBADAS</span></div>
<div class="metric"><strong>{{ $proposalSummary['executed'] }}</strong><span>EJECUTADAS</span></div>
<div class="metric"><strong>{{ $proposalSummary['stale'] }}</strong><span>DESACTUALIZADAS</span></div>
<div class="metric"><strong>{{ $proposalSummary['rejected'] }}</strong><span>RECHAZADAS</span></div>
</section>

<section class="grid">
<div class="panel shell-card">
<h2>Trabajar con CENTRAL</h2>
<div class="muted">Copilot orienta; las superficies operativas siguen siendo la fuente de trabajo y los cambios pasan por las capas seguras existentes.</div>
<div class="links">
<a class="quick-link" href="{{ route('daily-ops.show', array_filter(['scope'=>$selectedScope])) }}">Mi Día →</a>
<a class="quick-link" href="{{ route('decision-inbox.index', array_filter(['scope'=>$selectedScope])) }}">Decisiones →</a>
<a class="quick-link" href="{{ route('agent-proposals.index', array_filter(['scope'=>$selectedScope])) }}">Propuestas →</a>
<a class="quick-link" href="{{ route('automation-center.index') }}">Automatizaciones →</a>
<a class="quick-link" href="{{ route('executive-summary.show', array_filter(['scope'=>$selectedScope])) }}">Resumen →</a>
<a class="quick-link" href="{{ route('safety-recovery.index') }}">Estado y recuperación →</a>
</div>
</div>

<div class="panel shell-card">
<h2>Contrato de seguridad</h2>
<div class="muted">La pregunta nunca es una orden de ejecución.</div>
<div class="guard"><strong>Copilot 2.36:</strong> interpreta únicamente un catálogo cerrado de preguntas operativas. Si la pregunta no puede responderse con evidencia suficiente, lo dice. No inventa datos, no llama a una IA externa, no crea propuestas al consultar y no modifica entidades.</div>
<div class="guard">Para cambios deliberados, usa el Centro de propuestas. Allí se mantienen autorización por organización, protección stale, aprobación humana, segunda confirmación y undo cuando corresponde.</div>
</div>
</section>
</div>
<x-operational-interactions />
</body>
</html>
