from pathlib import Path


def replace_once(path: Path, needle: str, replacement: str) -> None:
    text = path.read_text()
    count = text.count(needle)
    if count != 1:
        raise SystemExit(
            f'{path}: esperaba 1 coincidencia y encontré {count}: {needle[:100]!r}'
        )
    path.write_text(text.replace(needle, replacement, 1))


controller = Path('app/Http/Controllers/DecisionInboxController.php')
replace_once(
    controller,
    "use App\\Support\\DecisionEngine;\nuse App\\Support\\ExecutiveSummaryBuilder;",
    "use App\\Support\\ControlledDelegationPolicy;\nuse App\\Support\\DecisionEngine;\nuse App\\Support\\ExecutiveSummaryBuilder;",
)
replace_once(
    controller,
    """        ExecutiveSummaryBuilder $builder,\n        DecisionEngine $engine,\n    ): View {\n""",
    """        ExecutiveSummaryBuilder $builder,\n        DecisionEngine $engine,\n        ControlledDelegationPolicy $delegationPolicy,\n    ): View {\n""",
)
replace_once(
    controller,
    """        $decisionEngine = $engine->evaluate($candidates->all());\n        $decisions = collect($decisionEngine['decisions']);\n\n        $counts = [\n""",
    """        $decisionEngine = $engine->evaluate($candidates->all());\n        $decisions = collect($decisionEngine['decisions'])\n            ->map(\n                fn (array $decision): array =>\n                    $decision + [\n                        'delegation' =>\n                            $delegationPolicy->evaluate(\n                                $decision,\n                            ),\n                    ],\n            );\n\n        $counts = [\n""",
)

routes = Path('routes/web.php')
replace_once(
    routes,
    """    )->name('decision-task-action.update');\n});\n\nRoute::middleware('auth')->group(function (): void {\n    Route::get(\n        '/resumen',\n""",
    """    )->name('decision-task-action.update');\n\n    Route::post(\n        '/decisiones/delegar',\n        [\n            \\App\\Http\\Controllers\\DecisionDelegationController::class,\n            'store',\n        ],\n    )->name('decision-delegation.store');\n});\n\nRoute::middleware('auth')->group(function (): void {\n    Route::get(\n        '/resumen',\n""",
)

view = Path('resources/views/decision-inbox.blade.php')
replace_once(
    view,
    """        .score-breakdown {\n            margin-top: 5px;\n            color: #64748b;\n            font-size: 10px;\n            line-height: 1.4;\n        }\n\n        .signals {\n""",
    """        .score-breakdown {\n            margin-top: 5px;\n            color: #64748b;\n            font-size: 10px;\n            line-height: 1.4;\n        }\n\n        .delegation-box {\n            margin-top: 12px;\n            padding: 11px;\n            border: 1px solid #1d4ed8;\n            border-radius: 12px;\n            background: rgba(37, 99, 235, .08);\n        }\n\n        .delegation-box.manual {\n            border-color: #334155;\n            background: rgba(15, 23, 42, .35);\n        }\n\n        .delegation-title {\n            color: #bfdbfe;\n            font-size: 11px;\n            font-weight: 850;\n        }\n\n        .delegation-box.manual .delegation-title { color: #cbd5e1; }\n\n        .delegation-copy {\n            margin-top: 4px;\n            color: #94a3b8;\n            font-size: 10px;\n            line-height: 1.45;\n        }\n\n        .delegation-actions {\n            display: flex;\n            flex-wrap: wrap;\n            gap: 6px;\n            margin-top: 9px;\n        }\n\n        .delegation-form {\n            display: grid;\n            gap: 7px;\n            margin-top: 9px;\n        }\n\n        .delegation-fields {\n            display: grid;\n            gap: 7px;\n        }\n\n        .delegation-input {\n            width: 100%;\n            min-width: 0;\n            min-height: 38px;\n            padding: 8px 10px;\n            border: 1px solid #334155;\n            border-radius: 9px;\n            background: #0f172a;\n            color: #f8fafc;\n        }\n\n        .delegation-action {\n            min-height: 36px;\n            padding: 7px 10px;\n            border: 1px solid #2563eb;\n            border-radius: 9px;\n            background: #172554;\n            color: #dbeafe;\n            font-size: 10px;\n            font-weight: 850;\n            cursor: pointer;\n        }\n\n        .signals {\n""",
)
replace_once(
    view,
    """            .why-now { color: #475569; }\n\n            .next-form input {\n""",
    """            .why-now { color: #475569; }\n            .delegation-box { background: #eff6ff; border-color: #93c5fd; }\n            .delegation-box.manual { background: #f8fafc; border-color: #cbd5e1; }\n            .delegation-title { color: #1d4ed8; }\n            .delegation-box.manual .delegation-title { color: #475569; }\n            .delegation-copy { color: #64748b; }\n            .delegation-input { background: #fff; color: #0f172a; border-color: #cbd5e1; }\n            .delegation-action { background: #eff6ff; color: #1d4ed8; border-color: #60a5fa; }\n\n            .next-form input {\n""",
)
replace_once(
    view,
    """                    @endif\n\n                    <a class=\"open-module\" href=\"{{ $decision['url'] }}\">\n                        Abrir módulo →\n                    </a>\n""",
    """                    @endif\n\n                    @php\n                        $delegation = $decision['delegation'];\n                    @endphp\n\n                    <div class=\"delegation-box {{ $delegation['can_delegate'] ? '' : 'manual' }}\">\n                        <div class=\"delegation-title\">\n                            {{ $delegation['status_label'] }} · Política v{{ $delegation['policy_version'] }}\n                        </div>\n                        <div class=\"delegation-copy\">\n                            {{ $delegation['reason'] }}\n                            Requiere revisión humana y una segunda confirmación antes de cualquier ejecución.\n                        </div>\n\n                        @if ($delegation['can_delegate'] && $decision['type'] === 'task')\n                            <div class=\"delegation-actions\">\n                                @foreach ($delegation['actions'] as $delegationAction)\n                                    <form\n                                        method=\"POST\"\n                                        action=\"{{ route('decision-delegation.store') }}\"\n                                    >\n                                        @csrf\n                                        <input type=\"hidden\" name=\"subject_type\" value=\"task\">\n                                        <input type=\"hidden\" name=\"subject_id\" value=\"{{ $decision['id'] }}\">\n                                        <input type=\"hidden\" name=\"action\" value=\"{{ $delegationAction['key'] }}\">\n                                        <button\n                                            class=\"delegation-action\"\n                                            type=\"submit\"\n                                            data-busy-label=\"Preparando…\"\n                                        >{{ $delegationAction['label'] }}</button>\n                                    </form>\n                                @endforeach\n                            </div>\n                        @elseif ($delegation['can_delegate'] && in_array($decision['type'], ['project', 'service'], true))\n                            @foreach ($delegation['actions'] as $delegationAction)\n                                <form\n                                    class=\"delegation-form\"\n                                    method=\"POST\"\n                                    action=\"{{ route('decision-delegation.store') }}\"\n                                >\n                                    @csrf\n                                    <input type=\"hidden\" name=\"subject_type\" value=\"{{ $decision['type'] }}\">\n                                    <input type=\"hidden\" name=\"subject_id\" value=\"{{ $decision['id'] }}\">\n                                    <input type=\"hidden\" name=\"action\" value=\"{{ $delegationAction['key'] }}\">\n\n                                    <div class=\"delegation-fields\">\n                                        <input\n                                            class=\"delegation-input\"\n                                            type=\"text\"\n                                            name=\"next_action\"\n                                            maxlength=\"255\"\n                                            required\n                                            placeholder=\"Siguiente acción concreta\"\n                                        >\n\n                                        @if ($decision['type'] === 'service')\n                                            <input\n                                                class=\"delegation-input\"\n                                                type=\"datetime-local\"\n                                                name=\"next_action_at\"\n                                            >\n                                        @endif\n                                    </div>\n\n                                    <button\n                                        class=\"delegation-action\"\n                                        type=\"submit\"\n                                        data-busy-label=\"Preparando…\"\n                                    >{{ $delegationAction['label'] }}</button>\n                                </form>\n                            @endforeach\n                        @endif\n                    </div>\n\n                    <a class=\"open-module\" href=\"{{ $decision['url'] }}\">\n                        Abrir módulo →\n                    </a>\n""",
)

print('2.30 Controlled Delegation UI + route + inbox aplicado correctamente')
