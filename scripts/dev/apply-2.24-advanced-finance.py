#!/usr/bin/env python3
from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: esperado 1 match, encontrados {count}")
    return text.replace(old, new, 1)


builder_path = Path('app/Support/ExecutiveSummaryBuilder.php')
view_path = Path('resources/views/executive-summary.blade.php')

builder = builder_path.read_text()

builder = replace_once(
    builder,
    """            'service_financial' => $serviceFinancial,
            'obligation_financial' =>
                $obligationFinancial,
""",
    """            'service_financial' => $serviceFinancial,
            'obligation_financial' =>
                $obligationFinancial,
            'executive_finance' => app(
                ExecutiveFinanceBuilder::class,
            )->build(
                $organizationIds,
                $selectedScope,
                $now,
            ),
""",
    'executive finance contract',
)

builder_path.write_text(builder)

view = view_path.read_text()

view = replace_once(
    view,
    """    <section class=\"section\">
        <div class=\"section-head\">
            <h2>Resumen financiero</h2>
""",
    """    <x-executive-finance
        :finance=\"$summary['executive_finance']\"
    />

    <section class=\"section\">
        <div class=\"section-head\">
            <h2>Resumen financiero</h2>
""",
    'executive finance component',
)

view_path.write_text(view)

print('2.24 patch aplicado correctamente')
