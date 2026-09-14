<?php

namespace Tests\Feature;

use App\Support\CentralCopilot;
use Tests\TestCase;

class CentralCopilotServiceTest extends TestCase
{
    public function test_copilot_normalizes_spanish_and_routes_critical_question_to_risks(): void
    {
        $result = app(CentralCopilot::class)->answer(
            '¿Qué es crítico ahora?',
            null,
            [
                'pressure_label' => 'alta',
                'pressure_score' => 88,
                'summary' => 'Hay presión operativa alta.',
            ],
            [
                'top_priorities' => [
                    [
                        'title' => 'Incidente de producción',
                        'organization' => 'ARPYNET',
                        'level_label' => 'Crítico',
                        'why' => 'SLA vencido',
                        'url' => '/incidentes',
                    ],
                ],
            ],
            [],
            [],
            [],
            'ARPYNET',
        );

        $this->assertSame('risks', $result['intent']);
        $this->assertSame('Riesgos y focos críticos', $result['title']);
        $this->assertStringContainsString('Incidente de producción', $result['items'][0]['title']);
        $this->assertTrue($result['read_only']);
    }

    public function test_copilot_today_uses_existing_daily_plan_without_inventing_items(): void
    {
        $result = app(CentralCopilot::class)->answer(
            '¿Qué debo hacer hoy?',
            null,
            [],
            [],
            [
                'summary' => 'Atiende primero el incidente.',
                'sections' => [
                    'immediate' => [
                        'items' => [
                            [
                                'title' => 'Incidente inmediato',
                                'organization' => 'ARPYNET',
                                'type_label' => 'Incidente',
                                'suggested_move' => 'Resolver',
                                'url' => '/incidentes',
                            ],
                        ],
                    ],
                ],
            ],
            [],
            [],
            'ARPYNET',
        );

        $this->assertSame('today', $result['intent']);
        $this->assertSame('Atiende primero el incidente.', $result['answer']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Incidente inmediato', $result['items'][0]['title']);
    }

    public function test_copilot_proposal_question_only_reports_existing_counts(): void
    {
        $result = app(CentralCopilot::class)->answer(
            '¿Qué propuestas tengo para aprobar?',
            null,
            [],
            [],
            [],
            [],
            [
                'pending' => 3,
                'approved' => 1,
                'executed' => 4,
                'stale' => 2,
            ],
            'ARPYNET',
        );

        $this->assertSame('proposals', $result['intent']);
        $this->assertStringContainsString('3 propuestas pendientes', $result['answer']);
        $this->assertStringContainsString('1 aprobadas', $result['answer']);
        $this->assertSame('agent-proposals.index', $result['cta']['route']);
        $this->assertTrue($result['read_only']);
    }

    public function test_copilot_unknown_intent_refuses_to_guess(): void
    {
        $result = app(CentralCopilot::class)->answer(
            'Predice el precio de una acción bursátil el próximo año',
            null,
            [],
            [],
            [],
            [],
            [],
            null,
        );

        $this->assertSame('unsupported', $result['intent']);
        $this->assertSame('No puedo responder eso con evidencia suficiente', $result['title']);
        $this->assertStringContainsString('No inventará', $result['answer']);
        $this->assertTrue($result['read_only']);
    }
}
