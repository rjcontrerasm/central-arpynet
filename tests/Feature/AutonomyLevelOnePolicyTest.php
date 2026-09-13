<?php

namespace Tests\Feature;

use App\Support\AutonomyLevelOnePolicy;
use Tests\TestCase;

class AutonomyLevelOnePolicyTest extends TestCase
{
    public function test_critical_immediate_high_evidence_pending_task_is_eligible(): void
    {
        $result = app(
            AutonomyLevelOnePolicy::class,
        )->evaluate(
            $this->decision(),
            'pending',
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('start', $result['action']);
        $this->assertTrue($result['prepares_proposal_only']);
        $this->assertFalse($result['auto_approval']);
        $this->assertFalse($result['auto_execution']);
        $this->assertSame('2.31.1', $result['policy_version']);
    }

    public function test_level_one_rejects_non_task_and_non_workable_statuses(): void
    {
        $policy = app(
            AutonomyLevelOnePolicy::class,
        );

        $project = $this->decision();
        $project['type'] = 'project';

        $this->assertFalse(
            $policy->eligible(
                $project,
                'pending',
            ),
        );

        $this->assertFalse(
            $policy->eligible(
                $this->decision(),
                'in_progress',
            ),
        );
    }

    public function test_level_one_requires_immediate_band_score_and_high_evidence(): void
    {
        $policy = app(
            AutonomyLevelOnePolicy::class,
        );

        $lowScore = $this->decision();
        $lowScore['decision_score'] = 84;

        $mediumEvidence = $this->decision();
        $mediumEvidence['evidence_quality'] = 'medium';

        $todayBand = $this->decision();
        $todayBand['decision_band'] = 'today';

        $this->assertFalse(
            $policy->eligible($lowScore, 'pending'),
        );
        $this->assertFalse(
            $policy->eligible(
                $mediumEvidence,
                'pending',
            ),
        );
        $this->assertFalse(
            $policy->eligible($todayBand, 'pending'),
        );
    }

    public function test_level_one_never_bypasses_controlled_delegation_policy(): void
    {
        $decision = $this->decision();
        $decision['reason_codes'] = [];
        $decision['level'] = 'attention';

        $result = app(
            AutonomyLevelOnePolicy::class,
        )->evaluate(
            $decision,
            'pending',
        );

        $this->assertFalse($result['eligible']);
        $this->assertNull($result['action']);
        $this->assertNotEmpty($result['reasons']);
    }

    private function decision(): array
    {
        return [
            'type' => 'task',
            'type_label' => 'Tarea',
            'id' => 10,
            'title' => 'Incidente crítico',
            'organization_id' => 1,
            'organization' => 'ARPYNET',
            'level' => 'critical',
            'level_label' => 'Crítico',
            'rank' => 100,
            'reasons' => [
                'Tarea vencida',
                'Sin siguiente acción',
            ],
            'reason_codes' => [
                'critical',
                'overdue',
                'missing_next_action',
            ],
            'decision_score' => 95,
            'decision_band' => 'immediate',
            'decision_band_label' =>
                'Decidir ahora',
            'evidence_quality' => 'high',
            'evidence_quality_label' =>
                'Evidencia alta',
            'why_now' =>
                'Tarea vencida · Sin siguiente acción',
            'stagnant' => false,
            'stagnation_days' => 0,
            'no_next_action' => true,
            'next_action' => null,
            'url' => '/mi-dia',
        ];
    }
}
