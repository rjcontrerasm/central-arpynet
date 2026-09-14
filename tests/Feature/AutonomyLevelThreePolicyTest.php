<?php

namespace Tests\Feature;

use App\Models\ServiceOrder;
use App\Support\AutonomyLevelThreePolicy;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AutonomyLevelThreePolicyTest extends TestCase
{
    public function test_high_confidence_overdue_invoice_without_next_action_is_eligible(): void
    {
        $result = app(AutonomyLevelThreePolicy::class)->evaluate(
            $this->decision(),
            $this->order(),
            CarbonImmutable::parse('2026-09-14 10:00:00'),
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame(
            'create_collection_task',
            $result['action'],
        );
        $this->assertSame(2, $result['daily_execution_limit']);
        $this->assertTrue($result['undo_required']);
        $this->assertFalse($result['source_mutation']);
        $this->assertFalse($result['external_network']);
        $this->assertFalse($result['external_messages']);
    }

    public function test_paid_or_not_overdue_invoice_is_rejected(): void
    {
        $policy = app(AutonomyLevelThreePolicy::class);
        $now = CarbonImmutable::parse('2026-09-14 10:00:00');

        $paid = $policy->evaluate(
            $this->decision(),
            $this->order(['paid_date' => '2026-09-14']),
            $now,
        );

        $notOverdue = $policy->evaluate(
            $this->decision(),
            $this->order(['invoice_due_date' => '2026-09-14']),
            $now,
        );

        $this->assertFalse($paid['eligible']);
        $this->assertFalse($notOverdue['eligible']);
    }

    public function test_invoice_evidence_and_missing_next_action_are_mandatory(): void
    {
        $policy = app(AutonomyLevelThreePolicy::class);
        $now = CarbonImmutable::parse('2026-09-14 10:00:00');

        $withoutEvidence = $policy->evaluate(
            $this->decision(),
            $this->order([
                'invoice_number' => null,
                'invoice_date' => null,
                'invoice_amount' => null,
            ]),
            $now,
        );

        $withNextAction = $policy->evaluate(
            $this->decision(),
            $this->order([
                'next_action' => 'Llamar al cliente',
            ]),
            $now,
        );

        $this->assertFalse($withoutEvidence['eligible']);
        $this->assertFalse($withNextAction['eligible']);
    }

    public function test_score_evidence_band_and_required_reason_codes_are_closed_gates(): void
    {
        $policy = app(AutonomyLevelThreePolicy::class);
        $now = CarbonImmutable::parse('2026-09-14 10:00:00');

        foreach ([
            ['decision_score' => 94],
            ['evidence_quality' => 'medium'],
            ['decision_band' => 'today'],
            ['level' => 'attention'],
            ['reason_codes' => ['critical', 'overdue', 'missing_next_action']],
        ] as $override) {
            $result = $policy->evaluate(
                array_replace($this->decision(), $override),
                $this->order(),
                $now,
            );

            $this->assertFalse($result['eligible']);
        }
    }

    public function test_only_invoiced_service_subject_is_supported(): void
    {
        $policy = app(AutonomyLevelThreePolicy::class);
        $now = CarbonImmutable::parse('2026-09-14 10:00:00');

        $wrongType = $policy->evaluate(
            array_replace($this->decision(), ['type' => 'project']),
            $this->order(),
            $now,
        );

        $wrongStage = $policy->evaluate(
            $this->decision(),
            $this->order(['stage' => 'conformity']),
            $now,
        );

        $this->assertFalse($wrongType['eligible']);
        $this->assertFalse($wrongStage['eligible']);
    }

    private function decision(): array
    {
        return [
            'type' => 'service',
            'level' => 'critical',
            'decision_score' => 99,
            'decision_band' => 'immediate',
            'evidence_quality' => 'high',
            'reason_codes' => [
                'critical',
                'missing_next_action',
                'overdue',
                'collection_risk',
            ],
        ];
    }

    private function order(array $overrides = []): ServiceOrder
    {
        $order = new ServiceOrder();
        $order->forceFill(array_replace([
            'title' => 'Servicio facturado',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-100',
            'invoice_date' => '2026-08-20',
            'invoice_due_date' => '2026-09-10',
            'invoice_amount' => 1500,
            'paid_date' => null,
            'next_action' => null,
        ], $overrides));

        return $order;
    }
}
