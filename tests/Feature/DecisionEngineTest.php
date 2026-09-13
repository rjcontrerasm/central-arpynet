<?php

namespace Tests\Feature;

use App\Support\DecisionEngine;
use Tests\TestCase;

class DecisionEngineTest extends TestCase
{
    public function test_engine_ranks_explicit_operational_risk_without_network_or_writes(): void
    {
        $result = app(DecisionEngine::class)->evaluate([
            $this->item(
                id: 1,
                title: 'Cobro vencido',
                level: 'critical',
                rank: 100,
                reasons: ['Cobro vencido'],
            ),
            $this->item(
                id: 2,
                title: 'Proyecto estancado',
                level: 'attention',
                rank: 75,
                reasons: ['Sin movimiento 20 días'],
                stagnant: true,
                stagnationDays: 20,
            ),
            $this->item(
                id: 3,
                title: 'Proyecto sin siguiente acción',
                level: 'watch',
                rank: 50,
                reasons: ['Sin siguiente acción'],
                noNextAction: true,
            ),
        ]);

        $this->assertSame('Cobro vencido', $result['decisions'][0]['title']);
        $this->assertSame(95, $result['decisions'][0]['decision_score']);
        $this->assertSame('immediate', $result['decisions'][0]['decision_band']);
        $this->assertContains('collection_risk', $result['decisions'][0]['reason_codes']);
        $this->assertSame(1, $result['decisions'][0]['decision_position']);
        $this->assertTrue($result['read_only']);
        $this->assertTrue($result['generated_without_network']);
        $this->assertSame('2.29.1', $result['score_version']);
    }

    public function test_engine_explains_score_and_evidence_quality(): void
    {
        $result = app(DecisionEngine::class)->evaluate([
            $this->item(
                id: 10,
                title: 'Servicio crítico sin acción',
                type: 'service',
                level: 'critical',
                rank: 92,
                reasons: ['Siguiente acción vencida', 'Cobro vencido'],
                noNextAction: true,
            ),
        ]);

        $decision = $result['decisions'][0];

        $this->assertSame([
            'operational_priority' => 92,
            'explicit_risk' => 100,
            'decision_gap' => 100,
        ], $decision['score_breakdown']);
        $this->assertSame('high', $decision['evidence_quality']);
        $this->assertSame('Evidencia alta', $decision['evidence_quality_label']);
        $this->assertStringContainsString('Siguiente acción vencida', $decision['why_now']);
        $this->assertTrue($decision['read_only_recommendation']);
    }

    public function test_engine_excludes_attention_that_does_not_require_a_decision(): void
    {
        $result = app(DecisionEngine::class)->evaluate([
            $this->item(
                id: 20,
                title: 'Seguimiento semanal',
                level: 'watch',
                rank: 55,
                reasons: ['Prioridad semanal'],
            ),
        ]);

        $this->assertSame([], $result['decisions']);
        $this->assertSame(0, $result['counts']['total']);
        $this->assertSame('No hay decisiones activas con las señales operativas actuales.', $result['summary']);
    }

    public function test_same_input_produces_same_decision_contract(): void
    {
        $items = [
            $this->item(
                id: 30,
                title: 'Tarea vencida',
                level: 'critical',
                rank: 100,
                reasons: ['Tarea vencida'],
            ),
        ];

        $engine = app(DecisionEngine::class);

        $this->assertSame(
            $engine->evaluate($items),
            $engine->evaluate($items),
        );
    }

    private function item(
        int $id,
        string $title,
        string $type = 'project',
        string $level = 'attention',
        int $rank = 75,
        array $reasons = [],
        bool $stagnant = false,
        int $stagnationDays = 0,
        bool $noNextAction = false,
    ): array {
        return [
            'type' => $type,
            'type_label' => ucfirst($type),
            'id' => $id,
            'title' => $title,
            'organization_id' => 1,
            'organization' => 'ARPYNET',
            'level' => $level,
            'level_label' => ucfirst($level),
            'rank' => $rank,
            'reasons' => $reasons,
            'stagnant' => $stagnant,
            'stagnation_days' => $stagnationDays,
            'no_next_action' => $noNextAction,
            'next_action' => null,
            'url' => '/test',
        ];
    }
}
