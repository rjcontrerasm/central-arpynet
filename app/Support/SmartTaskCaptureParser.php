<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SmartTaskCaptureParser
{
    /**
     * @param Collection<int, object> $organizations
     * @return array{
     *     title: string,
     *     due_mode: ?string,
     *     urgency: ?string,
     *     impact: ?string,
     *     waiting: bool,
     *     organization_id: ?int,
     *     interpretations: array<int, string>
     * }
     */
    public function parse(
        string $input,
        Collection $organizations,
    ): array {
        $text = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $input,
            ) ?? $input,
        );

        $result = [
            'title' => $text,
            'due_mode' => null,
            'urgency' => null,
            'impact' => null,
            'waiting' => false,
            'organization_id' => null,
            'interpretations' => [],
        ];

        if ($text === '') {
            return $result;
        }

        $organizations = $organizations
            ->sortByDesc(
                fn (object $organization): int =>
                    mb_strlen(
                        (string) $organization->name,
                    ),
            )
            ->values();

        for ($pass = 0; $pass < 10; $pass++) {
            $changed = false;

            foreach ($organizations as $organization) {
                $name = trim(
                    (string) $organization->name,
                );

                if ($name === '') {
                    continue;
                }

                $pattern =
                    '/^@'
                    .preg_quote($name, '/')
                    .'(?=$|[\s:,\-])'
                    .'[\s:,\-]*/iu';

                if (
                    preg_match(
                        $pattern,
                        $text,
                    ) === 1
                ) {
                    $text = trim(
                        preg_replace(
                            $pattern,
                            '',
                            $text,
                            1,
                        ) ?? $text,
                    );

                    $result['organization_id'] =
                        (int) $organization->id;

                    $this->addInterpretation(
                        $result,
                        'ámbito '.$name,
                    );

                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                continue;
            }

            $rules = [
                [
                    'pattern' =>
                        '/^(sin\s+fecha)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['due_mode'] = 'none';
                        $this->addInterpretation(
                            $result,
                            'sin fecha',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^((?:1|una)\s+semana|pr[oó]xima\s+semana|semana)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['due_mode'] =
                            'next_week';

                        $this->addInterpretation(
                            $result,
                            '1 semana',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(ma[nñ]ana)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['due_mode'] =
                            'tomorrow';

                        $this->addInterpretation(
                            $result,
                            'mañana',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(hoy)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['due_mode'] =
                            'today';

                        $this->addInterpretation(
                            $result,
                            'hoy',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(impacto\s+cr[ií]tico)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['impact'] =
                            'critical';

                        $this->addInterpretation(
                            $result,
                            'impacto crítico',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(alto\s+impacto|impacto\s+alto)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['impact'] =
                            'high';

                        $this->addInterpretation(
                            $result,
                            'impacto alto',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(cr[ií]tic[oa])\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['urgency'] =
                            'critical';

                        $result['impact'] =
                            'critical';

                        $this->addInterpretation(
                            $result,
                            'crítico',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(urgente)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['urgency'] =
                            'high';

                        $this->addInterpretation(
                            $result,
                            'urgente',
                        );
                    },
                ],
                [
                    'pattern' =>
                        '/^(esperando|en\s+espera)\b[\s:,\-]*/iu',
                    'apply' => function () use (&$result): void {
                        $result['waiting'] = true;

                        $this->addInterpretation(
                            $result,
                            'en espera',
                        );
                    },
                ],
            ];

            foreach ($rules as $rule) {
                if (
                    preg_match(
                        $rule['pattern'],
                        $text,
                    ) !== 1
                ) {
                    continue;
                }

                $text = trim(
                    preg_replace(
                        $rule['pattern'],
                        '',
                        $text,
                        1,
                    ) ?? $text,
                );

                $rule['apply']();
                $changed = true;
                break;
            }

            if (! $changed) {
                break;
            }
        }

        if (
            $result['urgency'] === 'critical'
            && $result['due_mode'] === null
            && ! $result['waiting']
        ) {
            $result['due_mode'] = 'today';

            $this->addInterpretation(
                $result,
                'hoy',
            );
        }

        if (
            $result['urgency'] === 'high'
            && $result['due_mode'] === null
            && ! $result['waiting']
        ) {
            $result['due_mode'] = 'today';

            $this->addInterpretation(
                $result,
                'hoy',
            );
        }

        $text = trim(
            preg_replace(
                '/^[\s:;,\-]+/u',
                '',
                $text,
            ) ?? $text,
        );

        if (
            $result['waiting']
            && $text !== ''
            && ! Str::startsWith(
                mb_strtolower($text),
                'esperando ',
            )
        ) {
            $text = 'Esperando '.$text;
        }

        $result['title'] = $text;

        return $result;
    }

    private function addInterpretation(
        array &$result,
        string $label,
    ): void {
        if (
            ! in_array(
                $label,
                $result['interpretations'],
                true,
            )
        ) {
            $result['interpretations'][] =
                $label;
        }
    }
}
