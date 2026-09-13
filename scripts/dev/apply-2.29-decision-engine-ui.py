from pathlib import Path

path = Path('resources/views/decision-inbox.blade.php')
text = path.read_text()

replacements = [
    (
        """        .reason {\n            margin-top: 5px;\n            color: #94a3b8;\n            font-size: 11px;\n            line-height: 1.45;\n        }\n\n        .signals {\n""",
        """        .reason {\n            margin-top: 5px;\n            color: #94a3b8;\n            font-size: 11px;\n            line-height: 1.45;\n        }\n\n        .engine-summary {\n            margin: -8px 0 18px;\n            padding: 11px 13px;\n            border: 1px solid #24304b;\n            border-radius: 12px;\n            background: #11182b;\n            color: #cbd5e1;\n            font-size: 12px;\n            line-height: 1.45;\n        }\n\n        .decision-engine-row {\n            display: flex;\n            flex-wrap: wrap;\n            align-items: center;\n            gap: 6px;\n            margin-top: 10px;\n        }\n\n        .decision-score, .decision-band, .evidence {\n            display: inline-flex;\n            align-items: center;\n            min-height: 25px;\n            padding: 4px 8px;\n            border-radius: 999px;\n            font-size: 10px;\n            font-weight: 850;\n        }\n\n        .decision-score { background: #172554; color: #bfdbfe; }\n        .decision-band { background: #1e293b; color: #e2e8f0; }\n        .evidence { background: #052e16; color: #bbf7d0; }\n\n        .why-now {\n            margin-top: 8px;\n            color: #cbd5e1;\n            font-size: 11px;\n            line-height: 1.45;\n        }\n\n        .score-breakdown {\n            margin-top: 5px;\n            color: #64748b;\n            font-size: 10px;\n            line-height: 1.4;\n        }\n\n        .signals {\n""",
    ),
    (
        """            .current-next { background: #eff6ff; color: #334155; }\n\n            .next-form input {\n""",
        """            .current-next { background: #eff6ff; color: #334155; }\n            .engine-summary { background: #fff; border-color: #e2e8f0; color: #475569; }\n            .decision-score { background: #eff6ff; color: #1d4ed8; }\n            .decision-band { background: #f1f5f9; color: #475569; }\n            .evidence { background: #ecfdf5; color: #047857; }\n            .why-now { color: #475569; }\n\n            .next-form input {\n""",
    ),
    (
        """            <h1>Decisiones</h1>\n            <div class=\"subtitle\">Resuelve lo que requiere una decisión concreta.</div>\n""",
        """            <h1>Decisiones</h1>\n            <div class=\"subtitle\">Decision Engine · priorización explicable y determinística.</div>\n""",
    ),
    (
        """    </section>\n\n    @php\n        $types = [\n""",
        """    </section>\n\n    <div class=\"engine-summary\">\n        <strong>{{ $decisionEngineSummary }}</strong>\n        · Score v{{ $decisionScoreVersion }}\n        · Recomendaciones de solo lectura; las acciones manuales siguen usando la capa operativa segura.\n    </div>\n\n    @php\n        $types = [\n""",
    ),
    (
        """        @foreach ([\n            'Decisiones' => $counts['total'],\n            'Críticas' => $counts['critical'],\n            'Sin próxima acción' => $counts['no_next_action'],\n            'Estancadas' => $counts['stagnant'],\n        ] as $label => $value)\n""",
        """        @foreach ([\n            'Decisiones' => $counts['total'],\n            'Decidir ahora' => $counts['immediate'],\n            'Decidir hoy' => $counts['today'],\n            'Evidencia alta' => $counts['high_evidence'],\n        ] as $label => $value)\n""",
    ),
    (
        """                    <div class=\"recommendation\">{{ $decision['recommended_action'] }}</div>\n                    <div class=\"reason\">{{ $decision['decision_reason'] }}</div>\n\n                    @if ($decision['reasons'])\n""",
        """                    <div class=\"decision-engine-row\">\n                        <span class=\"decision-score\">Score {{ $decision['decision_score'] }}/100</span>\n                        <span class=\"decision-band\">{{ $decision['decision_band_label'] }}</span>\n                        <span class=\"evidence\">{{ $decision['evidence_quality_label'] }}</span>\n                    </div>\n\n                    <div class=\"recommendation\">{{ $decision['recommended_action'] }}</div>\n                    <div class=\"reason\">{{ $decision['decision_reason'] }}</div>\n                    <div class=\"why-now\"><strong>Por qué ahora:</strong> {{ $decision['why_now'] }}</div>\n                    <div class=\"score-breakdown\">\n                        Prioridad {{ $decision['score_breakdown']['operational_priority'] }}\n                        · Riesgo {{ $decision['score_breakdown']['explicit_risk'] }}\n                        · Brecha {{ $decision['score_breakdown']['decision_gap'] }}\n                    </div>\n\n                    @if ($decision['reasons'])\n""",
    ),
]

for needle, replacement in replacements:
    count = text.count(needle)
    if count != 1:
        raise SystemExit(f'Esperaba 1 coincidencia y encontré {count}: {needle[:80]!r}')
    text = text.replace(needle, replacement, 1)

path.write_text(text)
print('2.29 Decision Engine UI aplicado correctamente')
