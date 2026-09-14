from pathlib import Path

NAV = Path('resources/views/components/operational-nav.blade.php')
TEST = Path('tests/Feature/OperationalNavigationTest.php')

nav = NAV.read_text()

label_anchor = "        'automations' => 'Automatizaciones',\n        'history' => 'Historial',"
label_replacement = "        'automations' => 'Automatizaciones',\n        'safety' => 'Estado y recuperación',\n        'history' => 'Historial',"
if label_anchor not in nav:
    raise SystemExit('No se encontro anchor secondaryLabels')
nav = nav.replace(label_anchor, label_replacement, 1)

menu_anchor = "            <a\n                class=\"{{ $active === 'automations' ? 'is-active' : '' }}\"\n                href=\"{{ route('automation-center.index') }}\"\n            >\n                Automatizaciones\n            </a>\n\n            <a\n                class=\"{{ $active === 'history' ? 'is-active' : '' }}\""
menu_replacement = "            <a\n                class=\"{{ $active === 'automations' ? 'is-active' : '' }}\"\n                href=\"{{ route('automation-center.index') }}\"\n            >\n                Automatizaciones\n            </a>\n\n            <a\n                class=\"{{ $active === 'safety' ? 'is-active' : '' }}\"\n                href=\"{{ route('safety-recovery.index') }}\"\n            >\n                Estado y recuperación\n            </a>\n\n            <a\n                class=\"{{ $active === 'history' ? 'is-active' : '' }}\""
if menu_anchor not in nav:
    raise SystemExit('No se encontro anchor de menu Automatizaciones/Historial')
nav = nav.replace(menu_anchor, menu_replacement, 1)
NAV.write_text(nav)

text = TEST.read_text()

page_anchor = "            '/automatizaciones',\n            '/historial',"
page_replacement = "            '/automatizaciones',\n            '/estado-recuperacion',\n            '/historial',"
if page_anchor not in text:
    raise SystemExit('No se encontro anchor pages en OperationalNavigationTest')
text = text.replace(page_anchor, page_replacement, 1)

assert_anchor = "                ->assertSee('Colaboración')\n                ->assertSee('Administración avanzada')"
assert_replacement = "                ->assertSee('Colaboración')\n                ->assertSee('Estado y recuperación')\n                ->assertSee('Administración avanzada')"
if assert_anchor not in text:
    raise SystemExit('No se encontro anchor de aserciones desktop')
text = text.replace(assert_anchor, assert_replacement, 1)

mobile_anchor = "            ->assertSee('Colaboración')\n            ->assertSee('Historial')"
mobile_replacement = "            ->assertSee('Colaboración')\n            ->assertSee('Estado y recuperación')\n            ->assertSee('Historial')"
if mobile_anchor not in text:
    raise SystemExit('No se encontro anchor de aserciones mobile')
text = text.replace(mobile_anchor, mobile_replacement, 1)

TEST.write_text(text)
print('2.34 navigation integration applied')
