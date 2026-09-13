#!/usr/bin/env python3
from pathlib import Path
import runpy

catalog_path = Path('app/Support/AutomationRuleCatalog.php')
catalog = catalog_path.read_text()

normalizations = [
    (
        "            ],            'service.conformity_ready' => [",
        "            ],\n            'service.conformity_ready' => [",
    ),
    (
        "                ],\n            ],\n            'service.invoice_overdue' => [",
        "                ],\n            ],\n\n            'service.invoice_overdue' => [",
    ),
    (
        "                ],\n            ],\n            'obligation.due_soon' => [",
        "                ],\n            ],\n\n            'obligation.due_soon' => [",
    ),
    (
        "                ],\n            ],\n            'waiting.followup_overdue' => [",
        "                ],\n            ],\n\n            'waiting.followup_overdue' => [",
    ),
]

for old, new in normalizations:
    if old in catalog:
        catalog = catalog.replace(old, new, 1)

catalog_path.write_text(catalog)

runpy.run_path(
    'scripts/dev/apply-2.23-cross-module-automation.py',
    run_name='__main__',
)
