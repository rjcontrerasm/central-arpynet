<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CentralAgentProposalQueue
{
    public function enqueue(
        User $actor,
        array $preview,
        ?string $rationale = null,
    ): AgentActionProposal {
        $normalized =
            $this->normalizePreview(
                $preview,
            );

        $this->authorize(
            $actor,
            $normalized['organization_id'],
        );

        if (
            $normalized[
                'confirmation_required'
            ] !== true
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta debe requerir confirmación explícita.',
            ]);
        }

        $fingerprint = hash(
            'sha256',
            implode('|', [
                $normalized[
                    'organization_id'
                ],
                $normalized['subject_type'],
                $normalized['subject_id'],
                $normalized['action_key'],
                json_encode(
                    $this->canonicalize(
                        $normalized[
                            'proposed_changes'
                        ],
                    ),
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
                ),
            ]),
        );

        $existing =
            AgentActionProposal::query()
                ->where(
                    'organization_id',
                    $normalized[
                        'organization_id'
                    ],
                )
                ->where(
                    'status',
                    'pending',
                )
                ->where(
                    'fingerprint',
                    $fingerprint,
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(
            function () use (
                $actor,
                $normalized,
                $rationale,
                $fingerprint,
            ): AgentActionProposal {
                $proposal =
                    AgentActionProposal::query()
                        ->create([
                            'organization_id' =>
                                $normalized[
                                    'organization_id'
                                ],
                            'created_by' =>
                                $actor->id,
                            'subject_type' =>
                                $normalized[
                                    'subject_type'
                                ],
                            'subject_id' =>
                                $normalized[
                                    'subject_id'
                                ],
                            'subject_title' =>
                                $normalized[
                                    'subject_title'
                                ],
                            'action_key' =>
                                $normalized[
                                    'action_key'
                                ],
                            'action_label' =>
                                $normalized[
                                    'action_label'
                                ],
                            'risk' =>
                                $normalized['risk'],
                            'effect' =>
                                $normalized['effect'],
                            'proposed_changes' =>
                                $normalized[
                                    'proposed_changes'
                                ],
                            'rationale' =>
                                trim(
                                    (string) $rationale,
                                ) !== ''
                                    ? trim(
                                        (string) $rationale,
                                    )
                                    : null,
                            'fingerprint' =>
                                $fingerprint,
                            'status' => 'pending',
                        ]);

                AuditLog::query()->create([
                    'organization_id' =>
                        $proposal
                            ->organization_id,
                    'user_id' => $actor->id,
                    'event' =>
                        'agent_proposal.created',
                    'subject_type' =>
                        'agent_action_proposal',
                    'subject_id' =>
                        $proposal->id,
                    'subject_label' =>
                        $proposal
                            ->subject_title,
                    'source' =>
                        'central_agent',
                    'changes' => [
                        'status' => [
                            'before' => null,
                            'after' => 'pending',
                        ],
                        'action_key' =>
                            $proposal->action_key,
                        'subject_type' =>
                            $proposal->subject_type,
                        'subject_id' =>
                            $proposal->subject_id,
                    ],
                    'occurred_at' => now(),
                ]);

                return $proposal;
            },
        );
    }

    private function normalizePreview(
        array $preview,
    ): array {
        $organizationId = (int) (
            $preview['organization_id']
            ?? 0
        );

        $action = trim(
            (string) (
                $preview['action'] ?? ''
            ),
        );

        $label = trim(
            (string) (
                $preview['label'] ?? ''
            ),
        );

        $risk = trim(
            (string) (
                $preview['risk'] ?? ''
            ),
        );

        $subjectType = trim(
            (string) (
                $preview['subject_type']
                ?? (
                    isset($preview['task_id'])
                        ? 'task'
                        : ''
                )
            ),
        );

        $subjectId = (int) (
            $preview['subject_id']
            ?? $preview['task_id']
            ?? 0
        );

        $subjectTitle = trim(
            (string) (
                $preview['subject_title']
                ?? $preview['task_title']
                ?? ''
            ),
        );

        $changes =
            $preview['proposed_changes']
            ?? $preview['changes']
            ?? null;

        if (
            $organizationId < 1
            || $action === ''
            || $label === ''
            || $risk === ''
            || $subjectType === ''
            || $subjectId < 1
            || $subjectTitle === ''
            || ! is_array($changes)
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'El preview no contiene un contrato de propuesta válido.',
            ]);
        }

        return [
            'organization_id' =>
                $organizationId,
            'subject_type' =>
                $subjectType,
            'subject_id' => $subjectId,
            'subject_title' =>
                $subjectTitle,
            'action_key' => $action,
            'action_label' => $label,
            'risk' => $risk,
            'effect' => trim(
                (string) (
                    $preview['effect']
                    ?? 'update'
                ),
            ) ?: 'update',
            'proposed_changes' =>
                $changes,
            'confirmation_required' =>
                (bool) (
                    $preview[
                        'confirmation_required'
                    ] ?? false
                ),
        ];
    }

    private function canonicalize(
        array $value,
    ): array {
        foreach (
            $value as $key => $item
        ) {
            if (is_array($item)) {
                $value[$key] =
                    $this->canonicalize(
                        $item,
                    );
            }
        }

        ksort($value);

        return $value;
    }

    private function authorize(
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
            ->where(
                'is_active',
                true,
            )
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para crear propuestas en este ámbito.',
            );
        }
    }
}
