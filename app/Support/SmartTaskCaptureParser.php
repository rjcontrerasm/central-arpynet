<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SmartTaskCaptureParser
{
    public function parse(
        string $input,
        Collection $organizations,
        ?Collection $projects = null,
        ?CarbonImmutable $now = null,
    ): array {
        $timezone = config('app.timezone', 'America/Lima');
        $now ??= CarbonImmutable::now($timezone);

        $text = trim(preg_replace('/\s+/u', ' ', $input) ?? $input);

        $result = [
            'title' => $text,
            'due_mode' => null,
            'due_date' => null,
            'urgency' => null,
            'impact' => null,
            'waiting' => false,
            'organization_id' => null,
            'project_id' => null,
            'next_action' => null,
            'interpretations' => [],
        ];

        if ($text === '') {
            return $result;
        }

        if (preg_match('/\s+->\s+(.+)$/u', $text, $match) === 1) {
            $nextAction = trim((string) $match[1]);

            if ($nextAction !== '') {
                $result['next_action'] = $nextAction;
                $text = trim(
                    preg_replace('/\s+->\s+(.+)$/u', '', $text, 1)
                    ?? $text,
                );
                $this->addInterpretation($result, 'próxima acción');
            }
        }

        $organizations = $organizations
            ->sortByDesc(fn (object $item): int => mb_strlen((string) $item->name))
            ->values();

        $projects = ($projects ?? collect())
            ->sortByDesc(fn (object $item): int => mb_strlen((string) $item->name))
            ->values();

        for ($pass = 0; $pass < 14; $pass++) {
            $changed = false;

            foreach ($organizations as $organization) {
                $name = trim((string) $organization->name);
                if ($name === '') {
                    continue;
                }

                $pattern = '/^@'.preg_quote($name, '/').'(?=$|[\s:,\-])[\s:,\-]*/iu';

                if (preg_match($pattern, $text) === 1) {
                    $text = trim(preg_replace($pattern, '', $text, 1) ?? $text);
                    $result['organization_id'] = (int) $organization->id;
                    $this->addInterpretation($result, 'ámbito '.$name);
                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                continue;
            }

            foreach ($projects as $project) {
                $name = trim((string) $project->name);
                if ($name === '') {
                    continue;
                }

                $pattern = '/^#'.preg_quote($name, '/').'(?=$|[\s:,\-])[\s:,\-]*/iu';

                if (preg_match($pattern, $text) === 1) {
                    $text = trim(preg_replace($pattern, '', $text, 1) ?? $text);
                    $result['project_id'] = (int) $project->id;
                    $this->addInterpretation($result, 'proyecto '.$name);
                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                continue;
            }

            if (preg_match('/^en\s+(\d{1,3})\s+d[ií]as?\b[\s:,\-]*/iu', $text, $match) === 1) {
                $days = max(0, min(365, (int) $match[1]));
                $this->applyExactDate($result, $now->startOfDay()->addDays($days));
                $text = trim(
                    preg_replace('/^en\s+\d{1,3}\s+d[ií]as?\b[\s:,\-]*/iu', '', $text, 1)
                    ?? $text,
                );
                continue;
            }

            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{4}))?\b[\s:,\-]*/u', $text, $match) === 1) {
                $date = $this->resolveNumericDate(
                    (int) $match[1],
                    (int) $match[2],
                    isset($match[3]) && $match[3] !== '' ? (int) $match[3] : null,
                    $now,
                );

                if ($date) {
                    $this->applyExactDate($result, $date);
                    $text = trim(
                        preg_replace('/^\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{4})?\b[\s:,\-]*/u', '', $text, 1)
                        ?? $text,
                    );
                    continue;
                }
            }

            if (preg_match(
                '/^(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)(?:\s+de\s+(\d{4}))?\b[\s:,\-]*/iu',
                $text,
                $match,
            ) === 1) {
                $date = $this->resolveNumericDate(
                    (int) $match[1],
                    $this->monthNumber(mb_strtolower($match[2])),
                    isset($match[3]) && $match[3] !== '' ? (int) $match[3] : null,
                    $now,
                );

                if ($date) {
                    $this->applyExactDate($result, $date);
                    $text = trim(
                        preg_replace(
                            '/^\d{1,2}\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)(?:\s+de\s+\d{4})?\b[\s:,\-]*/iu',
                            '',
                            $text,
                            1,
                        ) ?? $text,
                    );
                    continue;
                }
            }

            if (preg_match(
                '/^(?:este\s+|pr[oó]ximo\s+)?(lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo)\b[\s:,\-]*/iu',
                $text,
                $match,
            ) === 1) {
                $this->applyExactDate(
                    $result,
                    $this->nextWeekday($now, mb_strtolower($match[1])),
                );
                $text = trim(
                    preg_replace(
                        '/^(?:este\s+|pr[oó]ximo\s+)?(lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo)\b[\s:,\-]*/iu',
                        '',
                        $text,
                        1,
                    ) ?? $text,
                );
                continue;
            }

            $rules = [
                ['pattern' => '/^(sin\s+fecha)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['due_mode'] = 'none';
                    $result['due_date'] = null;
                    $this->addInterpretation($result, 'sin fecha');
                }],
                ['pattern' => '/^((?:1|una)\s+semana|pr[oó]xima\s+semana|semana)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['due_mode'] = 'next_week';
                    $result['due_date'] = null;
                    $this->addInterpretation($result, '1 semana');
                }],
                ['pattern' => '/^(ma[nñ]ana)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['due_mode'] = 'tomorrow';
                    $result['due_date'] = null;
                    $this->addInterpretation($result, 'mañana');
                }],
                ['pattern' => '/^(hoy)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['due_mode'] = 'today';
                    $result['due_date'] = null;
                    $this->addInterpretation($result, 'hoy');
                }],
                ['pattern' => '/^(impacto\s+cr[ií]tico)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['impact'] = 'critical';
                    $this->addInterpretation($result, 'impacto crítico');
                }],
                ['pattern' => '/^(alto\s+impacto|impacto\s+alto)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['impact'] = 'high';
                    $this->addInterpretation($result, 'impacto alto');
                }],
                ['pattern' => '/^(cr[ií]tic[oa])\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['urgency'] = 'critical';
                    $result['impact'] = 'critical';
                    $this->addInterpretation($result, 'crítico');
                }],
                ['pattern' => '/^(urgente)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['urgency'] = 'high';
                    $this->addInterpretation($result, 'urgente');
                }],
                ['pattern' => '/^(esperando|en\s+espera)\b[\s:,\-]*/iu', 'apply' => function () use (&$result): void {
                    $result['waiting'] = true;
                    $this->addInterpretation($result, 'en espera');
                }],
            ];

            foreach ($rules as $rule) {
                if (preg_match($rule['pattern'], $text) !== 1) {
                    continue;
                }

                $text = trim(preg_replace($rule['pattern'], '', $text, 1) ?? $text);
                $rule['apply']();
                $changed = true;
                break;
            }

            if (! $changed) {
                break;
            }
        }

        if (
            in_array($result['urgency'], ['high', 'critical'], true)
            && $result['due_mode'] === null
            && $result['due_date'] === null
            && ! $result['waiting']
        ) {
            $result['due_mode'] = 'today';
            $this->addInterpretation($result, 'hoy');
        }

        $text = trim(preg_replace('/^[\s:;,\-]+/u', '', $text) ?? $text);

        if (
            $result['waiting']
            && $text !== ''
            && ! Str::startsWith(mb_strtolower($text), 'esperando ')
        ) {
            $text = 'Esperando '.$text;
        }

        $result['title'] = $text;

        return $result;
    }

    private function applyExactDate(array &$result, CarbonImmutable $date): void
    {
        $result['due_mode'] = 'custom';
        $result['due_date'] = $date->toDateString();
        $this->addInterpretation($result, $date->format('d/m/Y'));
    }

    private function resolveNumericDate(
        int $day,
        int $month,
        ?int $year,
        CarbonImmutable $now,
    ): ?CarbonImmutable {
        $resolvedYear = $year ?? $now->year;

        if (! checkdate($month, $day, $resolvedYear)) {
            return null;
        }

        $date = CarbonImmutable::create(
            $resolvedYear,
            $month,
            $day,
            0,
            0,
            0,
            $now->timezone,
        );

        if ($year === null && $date->isBefore($now->startOfDay())) {
            $resolvedYear++;

            if (! checkdate($month, $day, $resolvedYear)) {
                return null;
            }

            $date = $date->setYear($resolvedYear);
        }

        return $date;
    }

    private function nextWeekday(CarbonImmutable $now, string $weekday): CarbonImmutable
    {
        $normalized = strtr($weekday, ['é' => 'e', 'á' => 'a']);

        $target = [
            'domingo' => 0,
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
        ][$normalized];

        $today = $now->startOfDay();
        $delta = ($target - $today->dayOfWeek + 7) % 7;

        return $today->addDays($delta);
    }

    private function monthNumber(string $month): int
    {
        return [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ][$month];
    }

    private function addInterpretation(array &$result, string $label): void
    {
        if (! in_array($label, $result['interpretations'], true)) {
            $result['interpretations'][] = $label;
        }
    }
}
