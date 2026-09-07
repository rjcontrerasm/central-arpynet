<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CentralAgentGateway
{
    public function __construct(
        private readonly OperationalTaskActionService $actions,
        private readonly ProjectActionProposalService $projectActions,
        private readonly ServiceOrderActionProposalService $serviceActions,
        private readonly CentralAgentProposalQueue $proposalQueue,
    ) {
    }

    /**
     * Contract is intentionally read/preview only.
     * There is no execute method in this gateway.
     */
    public function contract(): array
    {
        return [
            'contract' => 'central-agent-contract-v4',
            'scope' => [
                'task',
                'project',
                'service_order',
                'organization',
            ],
            'public_api' => false,
            'network_calls' => false,
            'write_execution' => false,
            'proposal_persistence' => true,
            'confirmation_required_for_future_writes' => true,
            'allowed_operations' => [
                'task.read',
                'task.action.preview',
                'task.action.propose',
                'project.read',
                'project.action.preview',
                'project.action.propose',
                'service_order.read',
                'service_order.action.preview',
                'service_order.action.propose',
                'organization.operational_context.read',
            ],
            'blocked_operations' => [
                'proposal.execute',
                'task.action.execute',
                'project.action.execute',
                'service_order.action.execute',
                'organization.action.execute',
                'entity.delete',
                'entity.bulk',
                'external.network',
            ],
            'action_catalog' => [
                'task' => $this->actions->catalog(),
                'project' => $this->projectActions->catalog(),
                'service_order' => $this->serviceActions->catalog(),
            ],
        ];
    }

    public function taskContext(
        User $actor,
        Task $task,
    ): array {
        $this->authorizeOrganization(
            $actor,
            (int) $task->organization_id,
        );

        $task->loadMissing([
            'organization',
            'project',
        ]);

        return [
            'type' => 'task',
            'id' => $task->id,
            'organization_id' => $task->organization_id,
            'organization' => $task->organization?->name,
            'project_id' => $task->project_id,
            'project' => $task->project?->name,
            'title' => $task->title,
            'status' => $task->status,
            'urgency' => $task->urgency,
            'impact' => $task->impact,
            'priority_score' => $task->priority_score,
            'priority_band' => $task->priority_band,
            'due_at' => $task->due_at?->toIso8601String(),
            'next_action' => $task->next_action,
            'waiting_until' =>
                $task->waiting_until?->toIso8601String(),
            'source' => $task->source,
            'url' => route(
                'daily-ops.show',
                ['scope' => $task->organization_id],
                false,
            ),
        ];
    }

    public function projectContext(
        User $actor,
        Project $project,
    ): array {
        $this->authorizeOrganization(
            $actor,
            (int) $project->organization_id,
        );

        $project->loadMissing('organization');
        $project->loadCount([
            'tasks',
            'tasks as completed_tasks_count' =>
                fn ($query) => $query->where(
                    'status',
                    'completed',
                ),
        ]);

        $signal = GlobalTrackingItemFactory::project(
            $project,
            CarbonImmutable::now(
                config('app.timezone', 'America/Lima'),
            ),
        );

        return [
            'type' => 'project',
            'id' => $project->id,
            'organization_id' => $project->organization_id,
            'organization' => $project->organization?->name,
            'name' => $project->name,
            'status' => $project->status,
            'type_key' => $project->type,
            'horizon' => $project->horizon,
            'progress_percent' =>
                $project->progress_percent,
            'target_date' =>
                $project->target_date?->toDateString(),
            'next_action' => $project->next_action,
            'blockers' => $project->blockers,
            'stagnation_days' =>
                $project->stagnation_days,
            'stagnation_label' =>
                $project->stagnation_label,
            'signal' => [
                'level' => $signal['level'],
                'level_label' =>
                    $signal['level_label'],
                'rank' => $signal['rank'],
                'reasons' => $signal['reasons'],
            ],
            'url' => route(
                'project-ops.show',
                [
                    'scope' => $project->organization_id,
                    'focus' => 'all',
                ],
                false,
            ),
        ];
    }

    public function serviceOrderContext(
        User $actor,
        ServiceOrder $order,
    ): array {
        $this->authorizeOrganization(
            $actor,
            (int) $order->organization_id,
        );

        $order->loadMissing([
            'organization',
            'client',
        ]);

        $signal = GlobalTrackingItemFactory::serviceOrder(
            $order,
            CarbonImmutable::now(
                config('app.timezone', 'America/Lima'),
            ),
        );

        return [
            'type' => 'service_order',
            'id' => $order->id,
            'organization_id' => $order->organization_id,
            'organization' => $order->organization?->name,
            'client_id' => $order->client_id,
            'client' => $order->client?->name,
            'title' => $order->title,
            'stage' => $order->stage,
            'stage_label' =>
                ServiceOrder::stageOptions()[$order->stage]
                ?? $order->stage,
            'amount' => $order->amount,
            'invoice_amount' => $order->invoice_amount,
            'currency' => $order->currency,
            'invoice_due_date' =>
                $order->invoice_due_date?->toDateString(),
            'paid_date' =>
                $order->paid_date?->toDateString(),
            'next_action' => $order->next_action,
            'next_action_at' =>
                $order->next_action_at?->toIso8601String(),
            'attention_label' =>
                $order->attention_label,
            'signal' => [
                'level' => $signal['level'],
                'level_label' =>
                    $signal['level_label'],
                'rank' => $signal['rank'],
                'reasons' => $signal['reasons'],
            ],
            'url' => route(
                'service-orders-ops.show',
                ['scope' => $order->organization_id],
                false,
            ),
        ];
    }

    public function organizationContext(
        User $actor,
        Organization $organization,
    ): array {
        $this->authorizeOrganization(
            $actor,
            (int) $organization->id,
        );

        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $tasks = Task::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereNotIn(
                'status',
                [
                    'completed',
                    'cancelled',
                    'someday',
                ],
            )
            ->with([
                'organization',
                'project',
            ])
            ->limit(250)
            ->get();

        $projects = Project::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            )
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($query) => $query->where(
                        'status',
                        'completed',
                    ),
            ])
            ->limit(200)
            ->get();

        $services = ServiceOrder::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            )
            ->with([
                'organization',
                'client',
            ])
            ->limit(200)
            ->get();

        $obligations = ObligationOccurrence::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('status', 'pending')
            ->with([
                'organization',
                'obligation',
            ])
            ->limit(200)
            ->get();

        $attention = collect()
            ->concat(
                $tasks->map(
                    fn (Task $task): array =>
                        GlobalTrackingItemFactory::task(
                            $task,
                            $now,
                        ),
                ),
            )
            ->concat(
                $projects->map(
                    fn (Project $project): array =>
                        GlobalTrackingItemFactory::project(
                            $project,
                            $now,
                        ),
                ),
            )
            ->concat(
                $services->map(
                    fn (ServiceOrder $order): array =>
                        GlobalTrackingItemFactory::serviceOrder(
                            $order,
                            $now,
                        ),
                ),
            )
            ->concat(
                $obligations->map(
                    fn (ObligationOccurrence $occurrence): array =>
                        GlobalTrackingItemFactory::obligation(
                            $occurrence,
                            $now,
                        ),
                ),
            )
            ->filter(
                fn (array $item): bool =>
                    GlobalTrackingItemFactory::needsAttention(
                        $item,
                    ),
            )
            ->sortByDesc('rank')
            ->take(12)
            ->map(
                fn (array $item): array => [
                    'type' => $item['type'],
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'level' => $item['level'],
                    'level_label' =>
                        $item['level_label'],
                    'rank' => $item['rank'],
                    'reasons' => $item['reasons'],
                    'meta' => $item['meta'],
                    'date_label' =>
                        $item['date_label'],
                    'url' => $item['url'],
                ],
            )
            ->values();

        return [
            'type' => 'organization',
            'id' => $organization->id,
            'name' => $organization->name,
            'counts' => [
                'clients' => Client::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('is_active', true)
                    ->count(),
                'services_open' =>
                    $services->count(),
                'projects_open' =>
                    $projects->count(),
                'tasks_open' =>
                    $tasks->count(),
                'obligations_pending' =>
                    $obligations->count(),
                'incidents_open' =>
                    Incident::query()
                        ->where(
                            'organization_id',
                            $organization->id,
                        )
                        ->open()
                        ->count(),
            ],
            'attention' => $attention->all(),
            'generated_at' => $now->toIso8601String(),
            'url' => route(
                'operational-360.show',
                ['scope' => $organization->id],
                false,
            ),
        ];
    }

    public function proposeTaskAction(
        User $actor,
        Task $task,
        string $action,
        ?string $rationale = null,
    ): \App\Models\AgentActionProposal {
        return $this->proposalQueue->enqueue(
            $actor,
            $this->previewTaskAction(
                $actor,
                $task,
                $action,
            ),
            $rationale,
        );
    }

    public function proposeProjectAction(
        User $actor,
        Project $project,
        string $action,
        array $payload = [],
        ?string $rationale = null,
    ): \App\Models\AgentActionProposal {
        return $this->proposalQueue->enqueue(
            $actor,
            $this->previewProjectAction(
                $actor,
                $project,
                $action,
                $payload,
            ),
            $rationale,
        );
    }

    public function proposeServiceOrderAction(
        User $actor,
        ServiceOrder $order,
        string $action,
        array $payload = [],
        ?string $rationale = null,
    ): \App\Models\AgentActionProposal {
        return $this->proposalQueue->enqueue(
            $actor,
            $this->previewServiceOrderAction(
                $actor,
                $order,
                $action,
                $payload,
            ),
            $rationale,
        );
    }
    public function previewProjectAction(
        User $actor,
        Project $project,
        string $action,
        array $payload = [],
    ): array {
        return $this->projectActions->preview(
            $actor,
            $project,
            $action,
            $payload,
        );
    }

    public function previewServiceOrderAction(
        User $actor,
        ServiceOrder $order,
        string $action,
        array $payload = [],
    ): array {
        return $this->serviceActions->preview(
            $actor,
            $order,
            $action,
            $payload,
        );
    }
    public function previewTaskAction(
        User $actor,
        Task $task,
        string $action,
    ): array {
        return $this->actions->preview(
            $actor,
            $task,
            $action,
        );
    }

    private function authorizeOrganization(
        User $actor,
        int $organizationId,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $actor->id,
            )
            ->where(
                'organization_id',
                $organizationId,
            )
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para consultar este ámbito.',
            );
        }
    }
}
