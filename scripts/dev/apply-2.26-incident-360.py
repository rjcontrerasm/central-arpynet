#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: Path, old: str, new: str, marker: str) -> None:
    text = path.read_text()

    if marker in text:
        print(f"{path}: ya integrado")
        return

    if old not in text:
        raise SystemExit(f"ERROR: no se encontro ancla en {path}")

    path.write_text(text.replace(old, new, 1))
    print(f"{path}: actualizado")


routes = Path("routes/web.php")
route_anchor = """Route::middleware('auth')->group(function (): void {
    Route::get(
        '/360',
        [
            \\App\\Http\\Controllers\\Operational360Controller::class,
            'show',
        ],
    )->name('operational-360.show');
});
"""
route_new = route_anchor + """
Route::middleware('auth')->group(function (): void {
    Route::get(
        '/incidentes',
        [
            \\App\\Http\\Controllers\\Incident360Controller::class,
            'index',
        ],
    )->name('incident-360.index');
});
"""
replace_once(
    routes,
    route_anchor,
    route_new,
    "incident-360.index",
)

nav = Path("resources/views/components/operational-nav.blade.php")
label_anchor = """        'overview360' => 'Vista 360',
"""
label_new = label_anchor + """        'incidents' => 'Incidentes 360',
"""
replace_once(
    nav,
    label_anchor,
    label_new,
    "'incidents' => 'Incidentes 360'",
)

text = nav.read_text()
menu_marker = "route('incident-360.index')"

if menu_marker not in text:
    menu_anchor = """            <a
                class=\"{{ $active === 'overview360' ? 'is-active' : '' }}\"
                href=\"{{ route('operational-360.show') }}\"
            >
                Vista 360
            </a>
"""
    menu_new = menu_anchor + """            <a
                class=\"{{ $active === 'incidents' ? 'is-active' : '' }}\"
                href=\"{{ route('incident-360.index') }}\"
            >
                Incidentes 360
            </a>
"""

    if menu_anchor not in text:
        raise SystemExit("ERROR: no se encontro ancla de menu operacional")

    nav.write_text(text.replace(menu_anchor, menu_new, 1))
    print(f"{nav}: enlace agregado")
else:
    print(f"{nav}: enlace ya integrado")

print("2.26 patch aplicado correctamente")
