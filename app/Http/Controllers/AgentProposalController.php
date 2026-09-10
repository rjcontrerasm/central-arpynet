<?php

namespace App\Http\Controllers;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Support\CentralAgentGateway;
use App\Support\CentralAgentProposalExecutor;
use App\Support\JarvisDailyPlan;
use App\Support\JarvisExecutivePrioritization;
use App\Support\JarvisOperationalIntelligence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgentProposalController extends Controller
{
    public function index(
        Request $request,
        CentralAgentGateway $gateway,
        JarvisOperationalIntelligence $intelligence,
        JarvisExecutivePrioritization $executivePrioritization,
        JarvisDailyPlan $dailyPlanService,
    ): View {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'all',
                    'pending',
                    'approved',
                    'rejected',
                    'executed',
                    'stale',
                ]),
            ],
        ]);

        $user = $request->user();

        $organizationIds =
            DB::table('organization_user')
                ->where(
                    'user_id',
                    $user->id,
                )
                ->where(
                    'is_active',
                    true,
                )
                ->pluck(
                    'organization_id',
                );

        $selectedScope =
            isset($validated['scope'])
                ? (int) $validated['scope']
                : null;

        if (
            $selectedScope
            && ! $organizationIds
                ->contains($selectedScope)
        ) {
            abort(403);
        }

        $selectedStatus =
            $validated['status']
            ?? 'pending';

        $organizations =
            $user->organizations()
                ->wherePivot(
                    'is_active',
                    true,
                )
                ->where(
                    'organizations.is_active',
                    true,
                )
                ->orderBy(
                    'organizations.name',
                )
                ->get([
                    'organizations.id',
                    'organizations.name',
                ]);

        $query =
            AgentActionProposal::query()
                ->visibleTo($user)
                ->with([
                    'organization',
                    'createdBy',
                    'reviewedBy',
                    'executedBy',
                    'undoAction',
                ]);

        if ($selectedScope) {
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($selectedStatus !== 'all') {
            $query->where(
                'status',
                $selectedStatus,
            );
        }

        $proposals = $query
            ->latest('id')
            ->limit(100)
            ->get();

        $baseProposalQuery =
            AgentActionProposal::query()
                ->visibleTo($user)
                ->when(
                    $selectedScope,
                    fn ($q) => $q->where(
                        'organization_id',
                        $selectedScope,
                    ),
                );

        $counts = (clone $baseProposalQuery)
            ->selectRaw(
                "status, COUNT(*) as total",
            )
            ->groupBy('status')
            ->pluck(
                'total',
                'status',
            );

        $summary = [
            'pending' =>
                (int) ($counts['pending'] ?? 0),
            'approved' =>
                (int) ($counts['approved'] ?? 0),
            'executed' =>
                (int) ($counts['executed'] ?? 0),
            'stale' =>
                (int) ($counts['stale'] ?? 0),
            'rejected' =>
                (int) ($counts['rejected'] ?? 0),
            'executed_recent' =>
                (clone $baseProposalQuery)
                    ->where(
                        'status',
                        'executed',
                    )
                    ->where(
                        'executed_at',
                        '>=',
                        now()->subDays(7),
                    )
                    ->count(),
            'undone' =>
                (clone $baseProposalQuery)
                    ->where(
                        'status',
                        'executed',
                    )
                    ->whereHas(
                        'undoAction',
                        fn ($q) => $q
                            ->whereNotNull(
                                'undone_at',
                            ),
                    )
                    ->count(),
        ];

        $contract = $gateway->contract();

        $focusOrganization = null;

        if ($selectedScope) {
            $focusOrganization =
                $organizations->firstWhere(
                    'id',
                    $selectedScope,
                );
        }

        if (! $focusOrganization) {
            $currentId = (int) (
                $user->current_organization_id
                ?? 0
            );

            if ($currentId > 0) {
                $focusOrganization =
                    $organizations->firstWhere(
                        'id',
                        $currentId,
                    );
            }
        }

        $focusOrganization ??=
            $organizations->first();

        $executiveContexts =
            $organizations
                ->map(
                    fn ($organization): array =>
                        $gateway
                            ->organizationContext(
                                $user,
                                $organization,
                            ),
                )
                ->all();

        $operationalContext =
            $focusOrganization
                ? collect(
                    $executiveContexts,
                )->first(
                    fn (array $context): bool =>
                        (int) $context['id']
                        === (int) $focusOrganization->id,
                )
                : null;

        $operationalIntelligence =
            $intelligence->analyze(
                $operationalContext,
            );

        $executivePrioritization =
            $executivePrioritization
                ->analyze(
                    $executiveContexts,
                );

        $dailyPlan =
            $dailyPlanService->build(
                $executivePrioritization,
            );

        $statusLabels = [
            'pending' => 'Pendientes',
            'approved' => 'Aprobadas',
            'rejected' => 'Rechazadas',
            'executed' => 'Ejecutadas',
            'stale' => 'Desactualizadas',
            'all' => 'Todas',
        ];

        return view(
            'agent-proposals',
            compact(
                'organizations',
                'selectedScope',
                'selectedStatus',
                'proposals',
                'counts',
                'summary',
                'contract',
                'focusOrganization',
                'operationalContext',
                'operationalIntelligence',
                'executivePrioritization',
                'dailyPlan',
                'statusLabels',
            ),
        );
    }

    public function prepare(
        Request $request,
        CentralAgentGateway $gateway,
    ): RedirectResponse {
        $validated = $request->validate([
            'subject_type' => [
                'required',
                Rule::in([
                    'task',
                    'project',
                    'service_order',
                ]),
            ],
            'subject_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'action' => [
                'required',
                Rule::in([
                    'complete',
                    'start',
                    'today',
                    'tomorrow',
                    'next_week',
                    'project.next_action.set',
                    'service_order.next_action.set',
                ]),
            ],
            'next_action' => [
                'nullable',
                'string',
                'max:255',
            ],
            'next_action_at' => [
                'nullable',
                'date',
            ],
        ]);

        $allowedByType = [
            'task' => [
                'complete',
                'start',
                'today',
                'tomorrow',
                'next_week',
            ],
            'project' => [
                'project.next_action.set',
            ],
            'service_order' => [
                'service_order.next_action.set',
            ],
        ];

        if (
            ! in_array(
                $validated['action'],
                $allowedByType[
                    $validated['subject_type']
                ],
                true,
            )
        ) {
            throw \Illuminate\Validation\ValidationException::
                withMessages([
                    'action' =>
                        'La acción propuesta no corresponde al tipo de entidad.',
                ]);
        }

        $result = DB::transaction(
            function () use (
                $request,
                $gateway,
                $validated,
            ): array {
                if (
                    $validated['subject_type']
                    === 'task'
                ) {
                    $task =
                        Task::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                (int) $validated[
                                    'subject_id'
                                ],
                            );

                    $this->authorizeOrganization(
                        $request,
                        (int) $task
                            ->organization_id,
                    );

                    if (
                        in_array(
                            $task->status,
                            [
                                'completed',
                                'cancelled',
                            ],
                            true,
                        )
                        || (
                            $validated['action']
                            === 'start'
                            && $task->status
                                === 'in_progress'
                        )
                    ) {
                        return [
                            'stale' => true,
                            'scope' =>
                                (int) $task
                                    ->organization_id,
                            'stale_message' =>
                                'La tarea cambió desde la lectura de Jarvis y esa preparación ya no es pertinente. Recarga Jarvis antes de continuar.',
                        ];
                    }

                    $proposal =
                        $gateway
                            ->proposeTaskAction(
                                $request->user(),
                                $task,
                                $validated['action'],
                                'Propuesta de tarea preparada manualmente desde Lectura Jarvis. La tarea permanece sin cambios hasta aprobación y segunda confirmación.',
                            );

                    return [
                        'stale' => false,
                        'scope' =>
                            (int) $task
                                ->organization_id,
                        'proposal' => $proposal,
                        'created' =>
                            $proposal
                                ->wasRecentlyCreated,
                    ];
                }

                if (
                    $validated['subject_type']
                    === 'project'
                ) {
                    $project =
                        Project::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                (int) $validated[
                                    'subject_id'
                                ],
                            );

                    $this->authorizeOrganization(
                        $request,
                        (int) $project
                            ->organization_id,
                    );

                    if (
                        filled(
                            $project->next_action,
                        )
                    ) {
                        return [
                            'stale' => true,
                            'scope' =>
                                (int) $project
                                    ->organization_id,
                            'stale_message' =>
                                'La sugerencia ya no está vigente porque el proyecto ya tiene una siguiente acción. Recarga Jarvis antes de preparar otra propuesta.',
                        ];
                    }

                    $proposal =
                        $gateway
                            ->proposeProjectAction(
                                $request->user(),
                                $project,
                                'project.next_action.set',
                                [
                                    'next_action' =>
                                        trim(
                                            (string) (
                                                $validated[
                                                    'next_action'
                                                ] ?? ''
                                            ),
                                        ),
                                ],
                                'Propuesta preparada manualmente desde Lectura Jarvis para definir la siguiente acción de un proyecto sin siguiente acción registrada.',
                            );

                    return [
                        'stale' => false,
                        'scope' =>
                            (int) $project
                                ->organization_id,
                        'proposal' => $proposal,
                        'created' =>
                            $proposal
                                ->wasRecentlyCreated,
                    ];
                }

                $order =
                    ServiceOrder::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            (int) $validated[
                                'subject_id'
                            ],
                        );

                $this->authorizeOrganization(
                    $request,
                    (int) $order
                        ->organization_id,
                );

                if (
                    filled(
                        $order->next_action,
                    )
                ) {
                    return [
                        'stale' => true,
                        'scope' =>
                            (int) $order
                                ->organization_id,
                        'stale_message' =>
                            'La sugerencia ya no está vigente porque el servicio ya tiene una siguiente acción. Recarga Jarvis antes de preparar otra propuesta.',
                    ];
                }

                $proposal =
                    $gateway
                        ->proposeServiceOrderAction(
                            $request->user(),
                            $order,
                            'service_order.next_action.set',
                            [
                                'next_action' =>
                                    trim(
                                        (string) (
                                            $validated[
                                                'next_action'
                                            ] ?? ''
                                        ),
                                    ),
                                'next_action_at' =>
                                    $validated[
                                        'next_action_at'
                                    ] ?? null,
                            ],
                            'Propuesta preparada manualmente desde Lectura Jarvis para definir la siguiente acción de un servicio sin siguiente acción registrada.',
                        );

                return [
                    'stale' => false,
                    'scope' =>
                        (int) $order
                            ->organization_id,
                    'proposal' => $proposal,
                    'created' =>
                        $proposal
                            ->wasRecentlyCreated,
                ];
            },
        );

        if (
            $result['stale']
            ?? false
        ) {
            return redirect()
                ->route(
                    'agent-proposals.index',
                    [
                        'scope' =>
                            $result['scope'],
                        'status' =>
                            'pending',
                    ],
                )
                ->with(
                    'agent_proposal_message',
                    $result['stale_message']
                    ?? 'La sugerencia ya no está vigente. Recarga Jarvis antes de preparar otra propuesta.',
                );
        }

        $message = (
            $result['created']
            ?? false
        )
            ? 'Propuesta preparada y enviada a Pendientes. Aún no se ejecutó ningún cambio.'
            : 'Ya existía una propuesta pendiente idéntica; CENTRAL reutilizó la existente. No se ejecutó ningún cambio.';

        return redirect()
            ->route(
                'agent-proposals.index',
                [
                    'scope' =>
                        $result['scope'],
                    'status' =>
                        'pending',
                ],
            )
            ->with(
                'agent_proposal_message',
                $message,
            );
    }

    public function approve(
        Request $request,
        AgentActionProposal $proposal,
    ): RedirectResponse {
        return $this->review(
            $request,
            $proposal,
            'approved',
        );
    }

    public function reject(
        Request $request,
        AgentActionProposal $proposal,
    ): RedirectResponse {
        return $this->review(
            $request,
            $proposal,
            'rejected',
        );
    }

    public function execute(
        Request $request,
        AgentActionProposal $proposal,
        CentralAgentProposalExecutor $executor,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirm_execution' => [
                'required',
                'accepted',
            ],
        ]);

        try {
            $result = $executor->execute(
                $request->user(),
                $proposal,
                (bool) (
                    $validated[
                        'confirm_execution'
                    ] ?? false
                ),
            );
        } catch (
            \Illuminate\Validation\ValidationException $exception
        ) {
            $message = collect(
                $exception->errors(),
            )
                ->flatten()
                ->first();

            return back()->with(
                'agent_proposal_message',
                $message
                    ?: 'La propuesta no pudo ejecutarse.',
            );
        }

        return redirect()
            ->route(
                'agent-proposals.index',
                [
                    'status' =>
                        ($result['stale'] ?? false)
                            ? 'stale'
                            : 'executed',
                ],
            )
            ->with(
                'agent_proposal_message',
                $result['message'],
            );
    }
    private function review(
        Request $request,
        AgentActionProposal $proposal,
        string $status,
    ): RedirectResponse {
        $this->authorizeProposal(
            $request,
            $proposal,
        );

        if ($proposal->status !== 'pending') {
            return back()->with(
                'agent_proposal_message',
                'La propuesta ya fue revisada.',
            );
        }

        DB::transaction(
            function () use (
                $request,
                $proposal,
                $status,
            ): void {
                $before =
                    $proposal->status;

                $proposal->forceFill([
                    'status' => $status,
                    'reviewed_by' =>
                        $request->user()->id,
                    'reviewed_at' => now(),
                ])->save();

                AuditLog::query()->create([
                    'organization_id' =>
                        $proposal
                            ->organization_id,
                    'user_id' =>
                        $request->user()->id,
                    'event' =>
                        'agent_proposal.'
                        .$status,
                    'subject_type' =>
                        'agent_action_proposal',
                    'subject_id' =>
                        $proposal->id,
                    'subject_label' =>
                        $proposal
                            ->subject_title,
                    'source' =>
                        'central_agent_review',
                    'changes' => [
                        'status' => [
                            'before' =>
                                $before,
                            'after' =>
                                $status,
                        ],
                        'action_key' =>
                            $proposal
                                ->action_key,
                    ],
                    'occurred_at' => now(),
                ]);
            },
        );

        if ($status === 'approved') {
            return redirect()
                ->route(
                    'agent-proposals.index',
                    ['status' => 'approved'],
                )
                ->with(
                    'agent_proposal_message',
                    'Propuesta aprobada. Revisa los cambios y usa “Ejecutar cambio” para aplicar una segunda confirmación humana.',
                );
        }

        return back()->with(
            'agent_proposal_message',
            'Propuesta rechazada. No se ejecutó ningún cambio.',
        );
    }

    private function authorizeProposal(
        Request $request,
        AgentActionProposal $proposal,
    ): void {
        $this->authorizeOrganization(
            $request,
            (int) $proposal
                ->organization_id,
        );
    }

    private function authorizeOrganization(
        Request $request,
        int $organizationId,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'organization_id',
                $organizationId,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
        );
    }
}
