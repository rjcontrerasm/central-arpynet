from pathlib import Path

path = Path('resources/views/operational-agenda.blade.php')
text = path.read_text()

needle = """        @endif
    </section>

    @if($isToday && $overdueItems->isNotEmpty())
"""

replacement = """        @endif

        <x-calendar-context :context=\"$calendarContext\" />
    </section>

    @if($isToday && $overdueItems->isNotEmpty())
"""

count = text.count(needle)
if count != 1:
    raise SystemExit(f'Esperaba 1 punto de integración de agenda; encontré {count}.')

path.write_text(text.replace(needle, replacement, 1))
print('2.28 calendar context UI aplicado correctamente')
