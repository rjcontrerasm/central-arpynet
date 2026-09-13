#!/usr/bin/env python3
from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: esperado 1 match, encontrados {count}")
    return text.replace(old, new, 1)


controller_path = Path('app/Http/Controllers/ServiceOrderOpsController.php')
summary_path = Path('app/Support/ExecutiveSummaryBuilder.php')
service_view_path = Path('resources/views/service-orders-ops.blade.php')
summary_view_path = Path('resources/views/executive-summary.blade.php')

controller = controller_path.read_text()
controller = replace_once(
    controller,
    "use App\\Support\\ServiceOrderFinancialState;\n",
    "use App\\Support\\ServiceOrderFinancialState;\nuse App\\Support\\ServiceHealthScore;\n",
    'service health import',
)
controller = replace_once(
    controller,
    """                foreach ($financial as $key => $value) {
                    $order->setAttribute(
                        'fin_'.$key,
                        $value,
                    );
                }
""",
    """                foreach ($financial as $key => $value) {
                    $order->setAttribute(
                        'fin_'.$key,
                        $value,
                    );
                }

                $health = ServiceHealthScore::evaluate(
                    $order,
                    $now,
                );

                foreach ($health as $key => $value) {
                    $order->setAttribute(
                        'health_'.$key,
                        $value,
                    );
                }
""",
    'service health evaluation',
)
controller_path.write_text(controller)

summary = summary_path.read_text()
summary = replace_once(
    summary,
    """            'executive_finance' => app(
                ExecutiveFinanceBuilder::class,
            )->build(
                $organizationIds,
                $selectedScope,
                $now,
            ),
""",
    """            'executive_finance' => app(
                ExecutiveFinanceBuilder::class,
            )->build(
                $organizationIds,
                $selectedScope,
                $now,
            ),
            'client_health' => app(
                ClientHealthScoreBuilder::class,
            )->build(
                $organizationIds,
                $selectedScope,
                $now,
            ),
""",
    'client health contract',
)
summary_path.write_text(summary)

service_view = service_view_path.read_text()
service_view = replace_once(
    service_view,
    """                    <span
                        class=\"pill {{ $order->fin_status }}\"
                    >
                        {{ $order->fin_label }}
                    </span>

                    <span class=\"pill\">
""",
    """                    <span
                        class=\"pill {{ $order->fin_status }}\"
                    >
                        {{ $order->fin_label }}
                    </span>

                    @if ($order->health_score !== null)
                        <span
                            class=\"pill {{ $order->health_css }}\"
                            title=\"Health Score del servicio\"
                        >
                            Health {{ $order->health_score }}/100
                            · {{ $order->health_label }}
                        </span>
                    @endif

                    <span class=\"pill\">
""",
    'service health badge',
)
service_view_path.write_text(service_view)

summary_view = summary_view_path.read_text()
summary_view = replace_once(
    summary_view,
    """    <x-executive-finance
        :finance=\"$summary['executive_finance']\"
    />
""",
    """    <x-client-health
        :clients=\"$summary['client_health']\"
    />

    <x-executive-finance
        :finance=\"$summary['executive_finance']\"
    />
""",
    'client health component',
)
summary_view_path.write_text(summary_view)

print('2.25 patch aplicado correctamente')
