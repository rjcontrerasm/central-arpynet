<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Jarvis · Central ARPYNET</title>
<x-operational-theme />
<style>
.jarvis{width:min(1050px,calc(100% - 28px));margin:0 auto;padding:24px 0 70px}
.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}
h1{margin:0 0 5px;font-size:clamp(32px,7vw,48px);letter-spacing:-.05em}
.sub,.meta,.empty{color:var(--op-muted,#94a3b8)}.sub{font-size:13px}
.notice{margin:0 0 14px;padding:11px 13px;border:1px solid #166534;border-radius:13px;background:#052e16;color:#bbf7d0;font-size:12px;font-weight:750}
.filters{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 17px}
.chip{padding:7px 10px;border:1px solid var(--op-border,#334155);border-radius:999px;color:inherit;text-decoration:none;font-size:11px;font-weight:800}
.chip.active{border-color:#60a5fa;background:#172554;color:#dbeafe}
.list{display:grid;gap:10px}.card{padding:14px;border:1px solid var(--op-border,#334155);border-radius:16px;background:var(--op-card,#0f172a)}
.row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.title{font-weight:850;line-height:1.3}.meta{margin-top:3px;font-size:11px;line-height:1.45}
.badges{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.badge{padding:4px 7px;border-radius:999px;background:#1e293b;color:#cbd5e1;font-size:10px;font-weight:850}
.badge.pending{background:#422006;color:#fde68a}.badge.approved{background:#052e16;color:#bbf7d0}.badge.rejected{background:#450a0a;color:#fecaca}
.reason{margin-top:9px;padding:8px 10px;border-left:3px solid #60a5fa;border-radius:8px;background:rgba(37,99,235,.08);font-size:12px}
.changes{margin-top:9px;display:grid;gap:5px}.change{padding:7px 9px;border-radius:9px;background:rgba(148,163,184,.08);font-size:11px;overflow-wrap:anywhere}
.actions{display:flex;gap:8px;margin-top:11px}.actions form{margin:0}.button{min-height:36px;padding:7px 11px;border-radius:9px;font:inherit;font-size:11px;font-weight:850;cursor:pointer}
.approve{border:1px solid #166534;background:#052e16;color:#bbf7d0}.reject{border:1px solid #7f1d1d;background:#450a0a;color:#fecaca}
.guard{margin-top:18px;padding:12px;border:1px dashed var(--op-border,#334155);border-radius:14px;color:var(--op-muted,#94a3b8);font-size:11px}
.empty{padding:20px;border:1px dashed var(--op-border,#334155);border-radius:15px;text-align:center;font-size:12px}
@media(max-width:620px){.head,.row{display:grid}}
@media(prefers-color-scheme:light){.chip.active{background:#eff6ff;color:#1d4ed8}.badge{background:#f1f5f9;color:#475569}.badge.pending{background:#fffbeb;color:#a16207}.badge.approved{background:#f0fdf4;color:#166534}.badge.rejected{background:#fef2f2;color:#b91c1c}.notice{background:#f0fdf4;color:#166534}.reason{background:#eff6ff}.approve{background:#f0fdf4;color:#166534}.reject{background:#fef2f2;color:#b91c1c}}
</style>
</head>
<body>
<div class="jarvis">
<header class="head">
<div>
<h1>Jarvis</h1>
<div class="sub">Propuestas operativas pendientes de revisión humana.</div>
</div>
<x-operational-nav active="agent" />
</header>

@if(session('agent_proposal_message'))
<div class="notice">{{ session('agent_proposal_message') }}</div>
@endif

<nav class="filters" aria-label="Filtrar propuestas">
@php
$baseScope=$selectedScope ? ['scope'=>$selectedScope] : [];
$statusLabels=[
'pending'=>'Pendientes',
'approved'=>'Aprobadas',
'rejected'=>'Rechazadas',
'all'=>'Todas',
];
@endphp
@foreach($statusLabels as $status=>$label)
<a class="chip {{ $selectedStatus===$status ? 'active' : '' }}" href="{{ route('agent-proposals.index',array_merge($baseScope,['status'=>$status])) }}">
{{ $label }}
@if($status!=='all')
· {{ (int)($counts[$status] ?? 0) }}
@endif
</a>
@endforeach
</nav>

<nav class="filters" aria-label="Filtrar por ámbito">
<a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('agent-proposals.index',['status'=>$selectedStatus]) }}">Todos los ámbitos</a>
@foreach($organizations as $organization)
<a class="chip {{ $selectedScope===$organization->id ? 'active' : '' }}" href="{{ route('agent-proposals.index',['scope'=>$organization->id,'status'=>$selectedStatus]) }}">{{ $organization->name }}</a>
@endforeach
</nav>

<div class="list">
@forelse($proposals as $proposal)
<article class="card">
<div class="row">
<div>
<div class="title">{{ $proposal->action_label }}</div>
<div class="meta">{{ $proposal->subject_title }} · {{ $proposal->organization?->name ?? 'Sin ámbito' }}</div>
</div>
<span class="badge {{ $proposal->status }}">{{ \App\Models\AgentActionProposal::statusOptions()[$proposal->status] ?? $proposal->status }}</span>
</div>

<div class="badges">
<span class="badge">{{ $proposal->subject_type }}</span>
<span class="badge">{{ $proposal->risk }}</span>
<span class="badge">{{ $proposal->effect }}</span>
</div>

@if($proposal->rationale)
<div class="reason"><strong>Motivo:</strong> {{ $proposal->rationale }}</div>
@endif

<div class="changes">
@foreach($proposal->proposed_changes as $key=>$value)
<div class="change">
<strong>{{ $key }}:</strong>
@if(is_array($value))
{{ json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}
@elseif(is_bool($value))
{{ $value ? 'sí' : 'no' }}
@elseif(is_null($value))
null
@else
{{ $value }}
@endif
</div>
@endforeach
</div>

<div class="meta">
Propuesta #{{ $proposal->id }} · {{ $proposal->created_at?->format('d/m/Y H:i') }}
@if($proposal->reviewed_at)
· revisada {{ $proposal->reviewed_at->format('d/m/Y H:i') }}
@endif
</div>

@if($proposal->status==='pending')
<div class="actions">
<form method="POST" action="{{ route('agent-proposals.approve',$proposal) }}">
@csrf
<button class="button approve" type="submit" data-confirm="¿Aprobar esta propuesta? Aún no se ejecutará el cambio.">Aprobar</button>
</form>
<form method="POST" action="{{ route('agent-proposals.reject',$proposal) }}">
@csrf
<button class="button reject" type="submit" data-confirm="¿Rechazar esta propuesta?">Rechazar</button>
</form>
</div>
@endif
</article>
@empty
<div class="empty">No hay propuestas en este filtro.</div>
@endforelse
</div>

<div class="guard">
<strong>Control activo:</strong>
aprobar una propuesta no modifica la tarea, proyecto o servicio. La ejecución seguirá bloqueada hasta la siguiente etapa de Jarvis.
</div>
</div>
<x-operational-interactions />
</body>
</html>
