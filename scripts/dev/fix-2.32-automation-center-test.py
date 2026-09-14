#!/usr/bin/env python3
from pathlib import Path

path = Path('tests/Feature/AutomationCenterTest.php')
text = path.read_text()
old = """            ->assertSee('El scheduler evalúa reglas activas')
            ->assertSee('Autonomía L1');"""
new = """            ->assertSee('Autonomía L1')
            ->assertSee('Autonomía L2');"""
count = text.count(old)
if count != 1:
    raise SystemExit(
        f"tests/Feature/AutomationCenterTest.php: esperaba 1 coincidencia y encontré {count}"
    )
path.write_text(text.replace(old, new, 1))
print('2.32 assertion de Automation Center alineada con copy L2')
