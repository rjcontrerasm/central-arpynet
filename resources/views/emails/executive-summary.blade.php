<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>
        {{ $period === 'week'
            ? 'Próximos 7 días'
            : 'Resumen de hoy' }}
    </title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f1f5f9;
        color:#0f172a;
        font-family:Arial,Helvetica,sans-serif;
    "
>
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="background:#f1f5f9;"
>
<tr>
<td align="center" style="padding:28px 14px;">
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        max-width:640px;
        background:#ffffff;
        border-radius:18px;
        overflow:hidden;
    "
>
<tr>
<td
    style="
        padding:26px 28px 16px;
        background:#0f172a;
        color:#ffffff;
    "
>
    <div
        style="
            font-size:12px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#94a3b8;
        "
    >
        Central ARPYNET
    </div>

    <h1
        style="
            margin:8px 0 0;
            font-size:28px;
            line-height:1.15;
        "
    >
        {{ $period === 'week'
            ? 'Próximos 7 días'
            : 'Resumen de hoy' }}
    </h1>

    <div
        style="
            margin-top:8px;
            font-size:13px;
            color:#cbd5e1;
        "
    >
        {{ \Carbon\CarbonImmutable::parse(
            $generatedAt
        )->timezone(
            config(
                'app.timezone',
                'America/Lima',
            )
        )->format('d/m/Y H:i') }}
    </div>
</td>
</tr>

<tr>
<td style="padding:24px 28px;">
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
>
<tr>
<td
    width="50%"
    style="
        padding:0 6px 12px 0;
        vertical-align:top;
    "
>
    <div
        style="
            padding:16px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fff7f7;
        "
    >
        <div
            style="
                font-size:26px;
                font-weight:800;
                color:#991b1b;
            "
        >
            {{ $counts['critical'] ?? 0 }}
        </div>
        <div
            style="
                margin-top:4px;
                font-size:12px;
                color:#7f1d1d;
            "
        >
            Críticos
        </div>
    </div>
</td>

<td
    width="50%"
    style="
        padding:0 0 12px 6px;
        vertical-align:top;
    "
>
    <div
        style="
            padding:16px;
            border:1px solid #fde68a;
            border-radius:12px;
            background:#fffbeb;
        "
    >
        <div
            style="
                font-size:26px;
                font-weight:800;
                color:#92400e;
            "
        >
            {{ $counts['attention'] ?? 0 }}
        </div>
        <div
            style="
                margin-top:4px;
                font-size:12px;
                color:#78350f;
            "
        >
            A vigilar
        </div>
    </div>
</td>
</tr>
</table>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        margin-top:8px;
        border-collapse:collapse;
    "
>
@php
    $rows = [
        'Tareas' => $counts['tasks_due'] ?? 0,
        'Seguimientos' => $counts['followups'] ?? 0,
        'Acciones de servicio' =>
            $counts['service_actions'] ?? 0,
        'Vencimientos' =>
            $counts['obligations'] ?? 0,
        'Proyectos por revisar' =>
            $counts['projects_review'] ?? 0,
    ];
@endphp

@foreach ($rows as $label => $value)
<tr>
<td
    style="
        padding:11px 0;
        border-bottom:1px solid #e2e8f0;
        font-size:14px;
        color:#475569;
    "
>
    {{ $label }}
</td>
<td
    align="right"
    style="
        padding:11px 0;
        border-bottom:1px solid #e2e8f0;
        font-size:15px;
        font-weight:800;
        color:#0f172a;
    "
>
    {{ $value }}
</td>
</tr>
@endforeach
</table>

<div style="padding-top:24px;">
    <a
        href="{{ $summaryUrl }}"
        style="
            display:inline-block;
            padding:12px 18px;
            border-radius:10px;
            background:#0f172a;
            color:#ffffff;
            font-size:13px;
            font-weight:700;
            text-decoration:none;
        "
    >
        Abrir resumen en Central
    </a>
</div>

<div
    style="
        margin-top:22px;
        font-size:11px;
        line-height:1.5;
        color:#94a3b8;
    "
>
    Este mensaje fue generado automáticamente por
    Central ARPYNET.
</div>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
