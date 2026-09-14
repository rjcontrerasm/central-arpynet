<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>CENTRAL Copilot · Central ARPYNET</title>
<x-operational-theme />
<style>
.copilot{width:min(1120px,calc(100% - 28px));margin:0 auto;padding:22px 0 78px}.topbar,.hero,.answer-head,.item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.topbar{align-items:center;margin-bottom:26px}.brand{font-weight:900;letter-spacing:-.03em;color:inherit;text-decoration:none}.hero{margin-bottom:16px}.hero h1{margin:0;font-size:clamp(34px,7vw,54px);line-height:.95;letter-spacing:-.055em}.subtitle,.muted{color:var(--central-muted);font-size:12px}.subtitle{margin-top:8px;max-width:720px;line-height:1.5}.shell-card{border:1px solid var(--central-border);border-radius:18px;background:var(--central-surface);box-shadow:var(--central-shadow)}
.ask{padding:15px;margin-bottom:14px}.ask-grid{display:grid;grid-template-columns:minmax(0,1fr) 190px auto;gap:8px}.ask input,.ask select{min-width:0;min-height:46px;padding:9px 11px;border:1px solid var(--central-border-strong);border-radius:11px;background:var(--central-surface);color:var(--central-text);font:inherit}.ask button{min-height:46px;padding:9px 15px;border:0;border-radius:11px;background:var(--central-primary);color:#fff;font:inherit;font-weight:900;cursor:pointer}.ask button:hover{background:var(--central-primary-hover)}.ask-note{margin-top:8px;color:var(--central-muted);font-size:10px}
.suggestions{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 14px}.suggestion{padding:7px 10px;border:1px solid var(--central-border);border-radius:999px;background:var(--central-surface);color:var(--central-primary-text);text-decoration:none;font-size:10px;font-weight:850}
.answer{padding:17px;margin-bottom:14px}.answer-head h2{margin:0;font-size:20px;letter-spacing:-.025em}.answer-badges{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}.badge{padding:5px 8px;border-radius:999px;background:var(--central-surface-soft);border:1px solid var(--central-border);font-size:9px;font-weight:900;color:var(--central-muted)}.badge.safe{background:var(--central-success-soft);color:var(--central-success);border-color:transparent}.answer-copy{margin-top:12px;font-size:14px;line-height:1.65}.items{display:grid;gap:8px;margin-top:14px}.item{padding:11px 12px;border:1px solid var(--central-border);border-radius:13px;background:var(--central-surface-soft)}.item-title{font-size:12px;font-weight:900;color:inherit;text-decoration:none}.item-meta{margin-top:4px;color:var(--central-muted);font-size:10px;line-height:1.45}.answer-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.action-link{display:inline-flex;align-items:center;min-height:38px;padding:8px 11px;border:1px solid #7eb0ff;border-radius:10px;background:var(--central-primary-soft);color:var(--central-primary-text);text-decoration:none;font-size:10px;font-weight:900}
.metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-bottom:14px}.metric{padding:12px;border:1px solid var(--central-border);border-radius:14px;background:var(--central-surface)}.metric strong{display:block;font-size:24px;line-height:1}.metric span{display:block;margin-top:5px;color:var(--central-muted);font-size:9px;font-weight:850}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.panel{padding:14px}.panel h2{margin:0 0 6px;font-size:15px}.links{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:10px}.quick-link{padding:10px;border:1px solid var(--central-border);border-radius:11px;color:inherit;text-decoration:none;font-size:10px;font-weight:850}.quick-link:hover{border-color:#7eb0ff}.guard{margin-top:10px;padding:10px 11px;border:1px dashed var(--central-border-strong);border-radius:11px;color:var(--central-muted);font-size:10px;line-height:1.5}
@media(max-width:760px){.topbar,.hero,.answer-head{display:grid}.ask-grid{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:1fr}.answer-badges{justify-content:flex-start}}@media(max-width:440px){.copilot{width:min(100% - 20px,1120px);padding-top:14px}.metrics,.links{grid-template-columns:1fr}.suggestion{width:100%;text-align:center}}
</style>
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
