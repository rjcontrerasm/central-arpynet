<?php

namespace App\Support;

class ExecutiveDecisionAdvisor
{
    /**
     * @return array{
     *     action: string,
     *     reason: string
     * }
     */
    public static function recommend(
        array $item,
    ): array {
        $reasons =
            $item['reasons'] ?? [];

        $contains = static function (
            string $needle,
        ) use ($reasons): bool {
            foreach ($reasons as $reason) {
                if (
                    str_contains(
                        mb_strtolower(
                            (string) $reason,
                        ),
                        mb_strtolower(
                            $needle,
                        ),
                    )
                ) {
                    return true;
                }
            }

            return false;
        };

        if (
            ($item['type'] ?? null)
            === 'task'
        ) {
            if (
                $contains('vencida')
                || $contains('vencido')
            ) {
                return [
                    'action' =>
                        'Resolver o reprogramar',
                    'reason' =>
                        'La tarea ya superó su fecha o seguimiento.',
                ];
            }

            if (
                (bool) (
                    $item[
                        'no_next_action'
                    ] ?? false
                )
            ) {
                return [
                    'action' =>
                        'Definir próxima acción',
                    'reason' =>
                        'Está abierta pero no tiene un siguiente paso claro.',
                ];
            }

            if (
                (bool) (
                    $item['stagnant']
                    ?? false
                )
            ) {
                return [
                    'action' =>
                        'Reactivar o cerrar',
                    'reason' =>
                        'Lleva demasiado tiempo sin movimiento.',
                ];
            }

            return [
                'action' =>
                    'Atender la tarea',
                'reason' =>
                    'Su prioridad requiere revisión.',
            ];
        }

        if (
            ($item['type'] ?? null)
            === 'project'
        ) {
            if ($contains('bloqueos')) {
                return [
                    'action' =>
                        'Resolver bloqueo',
                    'reason' =>
                        'El proyecto declara un bloqueo activo.',
                ];
            }

            if (
                (bool) (
                    $item[
                        'no_next_action'
                    ] ?? false
                )
            ) {
                return [
                    'action' =>
                        'Definir próxima acción',
                    'reason' =>
                        'El proyecto no tiene un siguiente paso definido.',
                ];
            }

            if (
                (bool) (
                    $item['stagnant']
                    ?? false
                )
            ) {
                return [
                    'action' =>
                        'Reactivar proyecto',
                    'reason' =>
                        'El proyecto acumula días sin actividad.',
                ];
            }

            return [
                'action' =>
                    'Revisar proyecto',
                'reason' =>
                    'Tiene una señal operativa que requiere atención.',
            ];
        }

        if (
            ($item['type'] ?? null)
            === 'service'
        ) {
            if ($contains('cobro')) {
                return [
                    'action' =>
                        'Gestionar cobranza',
                    'reason' =>
                        'Existe una factura vencida o pendiente de cobro.',
                ];
            }

            if (
                (bool) (
                    $item[
                        'no_next_action'
                    ] ?? false
                )
            ) {
                return [
                    'action' =>
                        'Definir próxima acción',
                    'reason' =>
                        'El servicio no tiene seguimiento operativo definido.',
                ];
            }

            if (
                (bool) (
                    $item['stagnant']
                    ?? false
                )
            ) {
                return [
                    'action' =>
                        'Mover la etapa',
                    'reason' =>
                        'El servicio lleva varios días sin actividad.',
                ];
            }

            return [
                'action' =>
                    'Revisar servicio',
                'reason' =>
                    'Tiene un hito o seguimiento que requiere atención.',
            ];
        }

        if (
            ($item['type'] ?? null)
            === 'obligation'
        ) {
            return [
                'action' =>
                    'Atender vencimiento',
                'reason' =>
                    'La obligación está vencida o próxima a vencer.',
            ];
        }

        return [
            'action' => 'Revisar',
            'reason' =>
                'Hay una señal operativa pendiente.',
        ];
    }

    public static function isDecision(
        array $item,
    ): bool {
        return (
            ($item['level'] ?? null)
            === 'critical'
        )
        || (bool) (
            $item['stagnant']
            ?? false
        )
        || (bool) (
            $item['no_next_action']
            ?? false
        )
        || self::hasDecisionReason(
            $item,
        );
    }

    private static function hasDecisionReason(
        array $item,
    ): bool {
        $decisionTerms = [
            'bloqueo',
            'cobro vencido',
            'siguiente acción vencida',
            'seguimiento de espera vencido',
            'fecha contractual vencida',
        ];

        foreach (
            $item['reasons'] ?? []
            as $reason
        ) {
            $normalized =
                mb_strtolower(
                    (string) $reason,
                );

            foreach (
                $decisionTerms
                as $term
            ) {
                if (
                    str_contains(
                        $normalized,
                        $term,
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
