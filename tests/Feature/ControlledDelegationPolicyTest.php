<?php

namespace Tests\Feature;

use App\Support\ControlledDelegationPolicy;
use Tests\TestCase;

class ControlledDelegationPolicyTest extends TestCase
{
    public function test_critical_overdue_task_can_only_prepare_safe_reactivation_or_rescheduling(): void
    {
        $policy = app(ControlledDelegationPolicy::class);

        $result = $policy->evaluate(
            $this->decision(
                type: 'task',
                reasonCodes: [
                    'critical',
                    'overdue',
                ],
                level: 'critical',
            ),
        );

        $keys = collect($result['actions'])
            ->pluck('key')
            ->all();

        $this->assertTrue($result['can_delegate']);
        $this->assertSame('delegable', $result['status']);
        $this->assertContains('start', $keys);
        $this->assertContains('today', $keys);
        $this->assertContains('tomorrow', $keys);
        $this->assertContains('next_week', $keys);
        $this->assertNotContains('complete', $keys);
        $this->assertTrue($result['requires_human_review']);
        $this->assertTrue($result['requires_execution_confirmation']);
        $this->assertFalse($result['autonomous_execution']);
    }

    public function test_project_without_next_action_can_only_prepare_next_action_proposal(): void
    {
        $result = app(ControlledDelegationPolicy::class)
            ->evaluate(
                $this->decision(
                    type: 'project',
                    reasonCodes: [
                        'missing_next_action',
                    ],
                ),
            );

        $this->assertTrue($result['can_delegate']);
        $this->assertSame(
            ['project.next_action.set'],
            collect($result['actions'])
                ->pluck('key')
                ->all(),
        );
        $this->assertSame(
            ['next_action'],
            $result['actions'][0]['requires'],
        );
    }

    public function test_collection_risk_without_missing_next_action_remains_manual(): void
    {
        $result = app(ControlledDelegationPolicy::class)
            ->evaluate(
                $this->decision(
                    type: 'service',
                    reasonCodes: [
                        'collection_risk',
                    ],
                ),
            );

        $this->assertFalse($result['can_delegate']);
        $this->assertSame('manual_only', $result['status']);
        $this->assertSame([], $result['actions']);
        $this->assertStringContainsString(
            'cobranza',
            mb_strtolower($result['reason']),
        );
    }

    public function test_obligation_remains_manual_in_230(): void
    {
        $result = app(ControlledDelegationPolicy::class)
            ->evaluate(
                $this->decision(
                    type: 'obligation',
                    reasonCodes: [
                        'critical',
                        'overdue',
                    ],
                    level: 'critical',
                ),
            );

        $this->assertFalse($result['can_delegate']);
        $this->assertSame('manual_only', $result['status']);
        $this->assertSame([], $result['actions']);
        $this->assertFalse($result['autonomous_execution']);
    }

    private function decision(
        string $type,
        array $reasonCodes,
        string $level = 'attention',
    ): array {
        return [
            'type' => $type,
            'level' => $level,
            'reason_codes' => $reasonCodes,
        ];
    }
}
