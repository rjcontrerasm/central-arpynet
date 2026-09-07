<?php

namespace App\Http\Controllers;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Support\CentralAgentProposalExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgentProposalController extends Controller
{
    public function index(
        Request $request,
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

        $counts =
            AgentActionProposal::query()
                ->visibleTo($user)
                ->when(
                    $selectedScope,
                    fn ($q) => $q->where(
                        'organization_id',
                        $selectedScope,
                    ),
                )
                ->selectRaw(
                    "status, COUNT(*) as total",
                )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status',
                );

        return view(
            'agent-proposals',
            compact(
                'organizations',
                'selectedScope',
                'selectedStatus',
                'proposals',
                'counts',
            ),
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
        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'organization_id',
                $proposal
                    ->organization_id,
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
