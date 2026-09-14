<?php

namespace Tests\Feature;

use App\Support\AutonomyLevelTwoPolicy;
use Tests\TestCase;

class AutonomyLevelTwoPolicyTest extends TestCase
{
    public function test_allows_only_critical_high_evidence_pending_task_with_score_at_least_92(): void
    {
        $result = app(
            AutonomyLevelTwoPolicy::class,
        )->evaluate(
            $this->decision(),
            'pending',
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('start', $result['action']);
        $this->assertTrue($result['auto_approval']);
        $this->assertTrue($result['auto_execution']);
        $this->assertTrue($result['undo_required']);
        $this->assertSame(3, $result['daily_execution_limit']);
        $this->assertSame('2.32.1', $result['policy_version']);
    }

    public function test_inbox_task_is_not_eligible_for_level_two(): void
    {
        $result = app(
            AutonomyLevelTwoPolicy::class,
        )->evaluate(
            $this->decision(),
            'inbox',
        );

        $this->assertFalse($result['eligible']);
        $this->assertNull($result['action']);
    }

    public function test_score_below_level_two_threshold_is_rejected(): void
    {
        $decision = $this->decision();
        $decision['decision_score'] = 91;

        $this->assertFalse(
            app(AutonomyLevelTwoPolicy::class)
                ->eligible($decision, 'pending'),
        );
    }

    public function test_medium_evidence_is_rejected(): void
    {
        $decision = $this->decision();
        $decision['evidence_quality'] = 'medium';

        $this->assertFalse(
            app(AutonomyLevelTwoPolicy::class)
                ->eligible($decision, 'pending'),
        );
    }

    public function test_non_immediate_decision_is_rejected(): void
    {
        $decision = $this->decision();
        $decision['decision_band'] = 'today';

        $this->assertFalse(
            app(AutonomyLevelTwoPolicy::class)
                ->eligible($decision, 'pending'),
        );
    }

    public function test_level_two_contract_does_not_allow_external_delete_bulk_or_cross_module_effects(): void
    {
        $result = app(
            AutonomyLevelTwoPolicy::class,
        )->evaluate(
            $this->decision(),
            'pending',
        );

        $this->assertFalse($result['external_network']);
        $this->assertFalse($result['deletes']);
        $this->assertFalse($result['bulk_execution']);
        $this->assertFalse($result['cross_module_creation']);
        $this->assertSame(['task'], $result['allowed_subject_types']);
        $this->assertSame(['pending'], $result['allowed_subject_statuses']);
        $this->assertSame(['start'], $result['allowed_actions']);
    }

    private function decision(): array
    {
        return [
            'type' => 'task',
            'level' => 'critical',
            'decision_score' => 96,
            'decision_band' => 'immediate',
            'decision_band_label' => 'Decidir ahora',
            'evidence_quality' => 'high',
            'evidence_quality_label' => 'Evidencia alta',
            'why_now' => 'Tarea vencida · Sin siguiente acción',
            'no_next_action' => true,
            'stagnant' => false,
            'reasons' => [
                'Tarea vencida',
                'Sin siguiente acción',
            ],
        ];
    }
}
