<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\DailyReviewSession;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\JarvisDailyReviewAssistant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisDailyReviewAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_assistant_classifies_loose_ends_and_reflects_official_review_progress(): void
    {
        $user = User::factory()->create();

        $review =
            DailyReviewSession::query()
                ->create([
                    'user_id' => $user->id,
                    'review_date' =>
                        '2026-09-10',
                    'decisions_reviewed_at' =>
                        '2026-09-10 17:00:00',
                    'tasks_reviewed_at' =>
                        '2026-09-10 17:05:00',
                ]);

        $assistant = app(
            JarvisDailyReviewAssistant::class,
        )->build(
            [
                'top_priorities' => [
                    $this->priority(
                        1,
                        'Casa Andina',
                        1,
                        'Tarea crítica',
                        'task',
                        'Tarea',
                        96,
                        'critical',
                        'Tarea vencida',
                        'Revisar el estado.',
                    ),
                    $this->priority(
                        2,
                        'SUNARP',
                        2,
                        'Seguimiento vencido',
                        'task',
                        'Tarea',
                        75,
                        'attention',
                        'Seguimiento de espera vencido',
                        'Resolver el seguimiento de espera.',
                    ),
                    $this->priority(
                        1,
                        'Casa Andina',
                        3,
                        'Proyecto sin acción',
                        'project',
                        'Proyecto',
                        72,
                        'attention',
                        'Sin siguiente acción',
                        'Definir una siguiente acción concreta.',
                        'project.next_action.set',
                    ),
                    $this->priority(
                        2,
                        'SUNARP',
                        4,
                        'Proyecto bloqueado',
                        'project',
                        'Proyecto',
                        68,
                        'attention',
                        'Tiene bloqueos · Proyecto estancado',
                        'Revisar el bloqueo.',
                    ),
                ],
            ],
            [
                'total_items' => 4,
            ],
            $review,
            CarbonImmutable::parse(
                '2026-09-10 18:00:00',
                'America/Lima',
            ),
        );

        $this->assertSame(
            1,
            $assistant['sections']
                ['critical']['count'],
        );

        $this->assertSame(
            1,
            $assistant['sections']
                ['follow_up']['count'],
        );

        $this->assertSame(
            1,
            $assistant['sections']
                ['next_action']['count'],
        );

        $this->assertSame(
            1,
            $assistant['sections']
                ['blocked']['count'],
        );

        $this->assertSame(
            2,
            $assistant['review']
                ['reviewed_count'],
        );

        $this->assertFalse(
            $assistant['review']
                ['completed'],
        );

        $this->assertTrue(
            $assistant['read_only'],
        );
    }

    public function test_opening_jarvis_shows_assisted_review_without_creating_review_session_or_proposal(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-10 18:00:00',
        );

        [$user, $organization] =
            $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Pendiente crítico al cierre',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto sin siguiente acción cierre',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $proposalBefore =
            AgentActionProposal::query()
                ->count();

        $reviewBefore =
            DailyReviewSession::query()
                ->count();

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'Cierre asistido Jarvis',
            )
            ->assertSee(
                'Críticos abiertos',
            )
            ->assertSee(
                'Sin siguiente acción',
            )
            ->assertSee(
                '0/4 revisados',
            )
            ->assertSee(
                'Abrir revisión diaria',
            )
            ->assertSee(
                'No crea sesiones de revisión',
                false,
            );

        $this->assertSame(
            $proposalBefore,
            AgentActionProposal::query()
                ->count(),
        );

        $this->assertSame(
            $reviewBefore,
            DailyReviewSession::query()
                ->count(),
        );
    }

    public function test_existing_daily_review_progress_is_visible_but_not_modified_by_jarvis(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-10 18:00:00',
        );

        [$user, $organization] =
            $this->context();

        $review =
            DailyReviewSession::query()
                ->create([
                    'user_id' =>
                        $user->id,
                    'review_date' =>
                        '2026-09-10',
                    'decisions_reviewed_at' =>
                        now(),
                    'waiting_reviewed_at' =>
                        now(),
                ]);

        $beforeUpdated =
            $review->updated_at?->toIso8601String();

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                '2/4 revisados',
            )
            ->assertSee(
                '✓ Decisiones y críticos',
            )
            ->assertSee(
                '✓ Seguimientos en espera',
            );

        $fresh = $review->fresh();

        $this->assertSame(
            2,
            $fresh->reviewedCount(),
        );

        $this->assertNull(
            $fresh->completed_at,
        );

        $this->assertSame(
            $beforeUpdated,
            $fresh->updated_at?->toIso8601String(),
        );
    }

    public function test_empty_assisted_review_is_safe(): void
    {
        $assistant = app(
            JarvisDailyReviewAssistant::class,
        )->build(
            [
                'top_priorities' => [],
            ],
            [
                'total_items' => 0,
            ],
            null,
        );

        $this->assertSame(
            0,
            $assistant[
                'unique_loose_ends'
            ],
        );

        $this->assertSame(
            0,
            $assistant['review']
                ['reviewed_count'],
        );

        $this->assertTrue(
            $assistant['read_only'],
        );
    }

    private function priority(
        int $organizationId,
        string $organization,
        int $id,
        string $title,
        string $type,
        string $typeLabel,
        int $rank,
        string $level,
        string $why,
        string $move,
        ?string $proposalAction = null,
    ): array {
        return [
            'organization_id' =>
                $organizationId,
            'organization' =>
                $organization,
            'organization_pressure' =>
                80,
            'type' => $type,
            'type_label' =>
                $typeLabel,
            'id' => $id,
            'title' => $title,
            'rank' => $rank,
            'level' => $level,
            'level_label' =>
                ucfirst($level),
            'why' => $why,
            'suggested_move' =>
                $move,
            'proposal_action' =>
                $proposalAction,
            'url' => '/jarvis',
        ];
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' =>
                    'ARPYNET Review Assistant',
                'slug' =>
                    'arpynet-review-assistant-v12',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [
            $user,
            $organization,
        ];
    }
}
