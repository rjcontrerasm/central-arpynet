<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DecisionEngine
{
    /**
     * Rank operational decision candidates without performing writes.
     *
     * @param iterable<int, array<string, mixed>> $items
     */
    public function evaluate(iterable $items): array
    {
        $decisions = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->filter(fn (array $item): bool => ExecutiveDecisionAdvisor::isDecision($item))
            ->map(fn (array $item): array => $this->decision($item))
            ->sort(function (array $left, array $right): int {
                return [
                    $right['decision_score'],
                    $right['rank'],
                    $left['organization'] ?? '',
                    $left['title'] ?? '',
                ] <=> [
                    $left['decision_score'],
                    $left['rank'],
                    $right['organization'] ?? '',
                    $right['title'] ?? '',
                ];
            })
            ->values()
            ->map(function (array $decision, int $index): array {
                $decision['decision_position'] = $index + 1;

                return $decision;
            });

        return [
            'summary' => $this->summary($decisions),
            'counts' => [
                'total' => $decisions->count(),
                'immediate' => $decisions->where('decision_band', 'immediate')->count(),
                'today' => $decisions->where('decision_band', 'today')->count(),
                'review' => $decisions->where('decision_band', 'review')->count(),
                'high_evidence' => $decisions->where('evidence_quality', 'high')->count(),
            ],
            'decisions' => $decisions->all(),
            'read_only' => true,
            'generated_without_network' => true,
            'score_version' => '2.29.1',
        ];
    }

    private function decision(array $item): array
    {
        $advice = ExecutiveDecisionAdvisor::recommend($item);
        $rank = $this->clamp((int) ($item['rank'] ?? 0));
        $risk = $this->riskScore($item);
        $decisionGap = $this->decisionGapScore($item);

        $score = $this->clamp((int) round(
            ($rank * 0.50)
            + ($risk * 0.30)
            + ($decisionGap * 0.20),
        ));

        return $item + [
            'recommended_action' => $advice['action'],
            'decision_reason' => $advice['reason'],
            'decision_score' => $score,
            'decision_band' => $this->band($score),
            'decision_band_label' => $this->bandLabel($score),
            'score_breakdown' => [
                'operational_priority' => $rank,
                'explicit_risk' => $risk,
                'decision_gap' => $decisionGap,
            ],
            'reason_codes' => $this->reasonCodes($item),
            'evidence_quality' => $this->evidenceQuality($item),
            'evidence_quality_label' => $this->evidenceQualityLabel($item),
            'why_now' => $this->whyNow($item),
            'decision_position' => 0,
            'read_only_recommendation' => true,
        ];
    }

    private function riskScore(array $item): int
    {
        $risk = match ((string) ($item['level'] ?? 'normal')) {
            'critical' => 90,
            'attention' => 65,
            'watch' => 40,
            default => 20,
        };

        $text = mb_strtolower(implode(' ', array_map(
            static fn (mixed $reason): string => (string) $reason,
            $item['reasons'] ?? [],
        )));

        foreach ([
            'vencid' => 100,
            'atras' => 100,
            'cobro' => 95,
            'bloqueo' => 90,
            'crític' => 85,
            'espera' => 80,
            'sin siguiente acción' => 65,
            'sin próxima acción' => 65,
        ] as $needle => $score) {
            if (str_contains($text, $needle)) {
                $risk = max($risk, $score);
            }
        }

        if ((bool) ($item['stagnant'] ?? false)) {
            $days = max(0, (int) ($item['stagnation_days'] ?? 0));
            $risk = max($risk, $days >= 30 ? 85 : 70);
        }

        return $this->clamp($risk);
    }

    private function decisionGapScore(array $item): int
    {
        if ((bool) ($item['no_next_action'] ?? false)) {
            return 100;
        }

        if ((bool) ($item['stagnant'] ?? false)) {
            return ((int) ($item['stagnation_days'] ?? 0)) >= 30 ? 90 : 75;
        }

        if (($item['level'] ?? null) === 'critical') {
            return 75;
        }

        if ($this->hasExplicitDecisionReason($item)) {
            return 70;
        }

        return 45;
    }

    private function reasonCodes(array $item): array
    {
        $codes = [];

        if (($item['level'] ?? null) === 'critical') {
            $codes[] = 'critical';
        }

        if ((bool) ($item['no_next_action'] ?? false)) {
            $codes[] = 'missing_next_action';
        }

        if ((bool) ($item['stagnant'] ?? false)) {
            $codes[] = 'stagnant';
        }

        $text = mb_strtolower(implode(' ', array_map(
            static fn (mixed $reason): string => (string) $reason,
            $item['reasons'] ?? [],
        )));

        $terms = [
            'vencid' => 'overdue',
            'atras' => 'overdue',
            'cobro' => 'collection_risk',
            'bloqueo' => 'blocked',
            'espera' => 'waiting_followup',
        ];

        foreach ($terms as $needle => $code) {
            if (str_contains($text, $needle)) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function evidenceQuality(array $item): string
    {
        $reasons = array_values(array_filter(
            $item['reasons'] ?? [],
            static fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '',
        ));

        $structuredSignals = (int) ((bool) ($item['no_next_action'] ?? false))
            + (int) ((bool) ($item['stagnant'] ?? false))
            + (int) (($item['level'] ?? null) === 'critical');

        if (count($reasons) >= 2 || ($reasons !== [] && $structuredSignals >= 1)) {
            return 'high';
        }

        if ($reasons !== [] || $structuredSignals >= 1) {
            return 'medium';
        }

        return 'limited';
    }

    private function evidenceQualityLabel(array $item): string
    {
        return match ($this->evidenceQuality($item)) {
            'high' => 'Evidencia alta',
            'medium' => 'Evidencia media',
            default => 'Evidencia limitada',
        };
    }

    private function whyNow(array $item): string
    {
        $reasons = array_values(array_filter(
            $item['reasons'] ?? [],
            static fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '',
        ));

        if ($reasons !== []) {
            return implode(' · ', array_slice($reasons, 0, 3));
        }

        if ((bool) ($item['no_next_action'] ?? false)) {
            return 'No existe una próxima acción registrada.';
        }

        if ((bool) ($item['stagnant'] ?? false)) {
            return 'El elemento acumula inactividad registrada.';
        }

        return 'La señal operativa existente requiere una decisión.';
    }

    private function hasExplicitDecisionReason(array $item): bool
    {
        $text = mb_strtolower(implode(' ', array_map(
            static fn (mixed $reason): string => (string) $reason,
            $item['reasons'] ?? [],
        )));

        foreach (['bloqueo', 'cobro vencido', 'vencid', 'espera'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function band(int $score): string
    {
        return match (true) {
            $score >= 85 => 'immediate',
            $score >= 70 => 'today',
            $score >= 55 => 'review',
            default => 'monitor',
        };
    }

    private function bandLabel(int $score): string
    {
        return match ($this->band($score)) {
            'immediate' => 'Decidir ahora',
            'today' => 'Decidir hoy',
            'review' => 'Revisar',
            default => 'Monitorear',
        };
    }

    private function summary(Collection $decisions): string
    {
        if ($decisions->isEmpty()) {
            return 'No hay decisiones activas con las señales operativas actuales.';
        }

        $immediate = $decisions->where('decision_band', 'immediate')->count();
        $today = $decisions->where('decision_band', 'today')->count();

        $parts = [];
        if ($immediate > 0) {
            $parts[] = $immediate.' para decidir ahora';
        }
        if ($today > 0) {
            $parts[] = $today.' para decidir hoy';
        }

        $summary = $decisions->count().' decisión'.($decisions->count() === 1 ? '' : 'es').' activa'.($decisions->count() === 1 ? '' : 's');

        return $parts === []
            ? $summary.' requieren revisión.'
            : $summary.': '.implode(' y ', $parts).'.';
    }

    private function clamp(int $score): int
    {
        return min(100, max(0, $score));
    }
}
