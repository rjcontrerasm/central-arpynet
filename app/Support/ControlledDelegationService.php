<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlledDelegationService
{
    public function __construct(
        private readonly DecisionEngine $engine,
        private readonly ControlledDelegationPolicy $policy,
        private readonly CentralAgentGateway $gateway,
    ) {
    }

    /**
     * Prepare a pending proposal from a decision that is still current.
     * This method never approves or executes the proposal.
     */
    public function delegate(
        User $actor,
        string $subjectType,
        int $subjectId,
        string $action,
        array $payload = [],
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        return DB::transaction(function () use (
            $actor,
            $subjectType,
            $subjectId,
            $action,
            $payload,
            $now,
        ): array {
            $subject = $this->lockSubject(
                $subjectType,
                $subjectId,
            );

            $this->authorizeOrganization(
                $actor,
                (int) $subject->organization_id,
            );

            $item = $this->trackingItem(
                $subjectType,
                $subject,
                $now,
            );

            $decision = collect(
                $this->engine->evaluate([$item])['decisions'] ?? [],
            )->first();

            if (! is_array($decision)) {
                throw ValidationException::withMessages([
                    'delegation' =>
                        'La decisión ya no está vigente. Recarga Decisiones antes de preparar una delegación.',
                ]);
            }

            $delegation = $this->policy->evaluate(
                $decision,
            );

            if (! $this->policy->allows(
                $decision,
                $action,
            )) {
                throw ValidationException::withMessages([
                    'action' =>
                        'La acción solicitada no está permitida por la política de delegación controlada actual.',
                ]);
            }

            $rationale = $this->rationale(
                $decision,
            );

            $proposal = match ($subjectType) {
                'task' => $this->gateway->proposeTaskAction(
                    $actor,
                    $subject,
                    $action,
                    $rationale,
                ),
                'project' => $this->gateway->proposeProjectAction(
                    $actor,
                    $subject,
                    $action,
                    $payload,
                    $rationale,
                ),
                'service' => $this->gateway->proposeServiceOrderAction(
                    $actor,
                    $subject,
                    $action,
                    $payload,
                    $rationale,
                ),
                default => throw ValidationException::withMessages([
                    'subject_type' =>
                        'El tipo de entidad no admite delegación controlada.',
                ]),
            };

            return [
                'proposal' => $proposal,
                'created' => $proposal->wasRecentlyCreated,
                'decision' => $decision,
                'delegation' => $delegation,
                'entity_unchanged' => true,
                'execution_performed' => false,
            ];
        });
    }

    private function lockSubject(
        string $subjectType,
        int $subjectId,
    ): Model {
        if ($subjectId < 1) {
            throw ValidationException::withMessages([
                'subject_id' =>
                    'La entidad de delegación no es válida.',
            ]);
        }

        return match ($subjectType) {
            'task' => Task::query()
                ->whereKey($subjectId)
                ->lockForUpdate()
                ->firstOrFail(),
            'project' => Project::query()
                ->whereKey($subjectId)
                ->lockForUpdate()
                ->firstOrFail(),
            'service' => ServiceOrder::query()
                ->whereKey($subjectId)
                ->lockForUpdate()
                ->firstOrFail(),
            default => throw ValidationException::withMessages([
                'subject_type' =>
                    'El tipo de entidad no admite delegación controlada.',
            ]),
        };
    }

    private function trackingItem(
        string $subjectType,
        Model $subject,
        CarbonImmutable $now,
    ): array {
        return match ($subjectType) {
            'task' => $this->taskItem($subject, $now),
            'project' => $this->projectItem($subject, $now),
            'service' => $this->serviceItem($subject, $now),
            default => throw ValidationException::withMessages([
                'subject_type' =>
                    'El tipo de entidad no admite delegación controlada.',
            ]),
        };
    }

    private function taskItem(
        Model $subject,
        CarbonImmutable $now,
    ): array {
        /** @var Task $subject */
        $subject->loadMissing('organization');

        return GlobalTrackingItemFactory::task(
            $subject,
            $now,
        );
    }

    private function projectItem(
        Model $subject,
        CarbonImmutable $now,
    ): array {
        /** @var Project $subject */
        $subject->loadMissing('organization');
        $subject->loadCount([
            'tasks',
            'tasks as completed_tasks_count' =>
                fn ($query) => $query->where(
                    'status',
                    'completed',
                ),
        ]);

        return GlobalTrackingItemFactory::project(
            $subject,
            $now,
        );
    }

    private function serviceItem(
        Model $subject,
        CarbonImmutable $now,
    ): array {
        /** @var ServiceOrder $subject */
        $subject->loadMissing('organization');

        return GlobalTrackingItemFactory::serviceOrder(
            $subject,
            $now,
        );
    }

    private function rationale(array $decision): string
    {
        return implode(' ', [
            'Delegación controlada 2.30 preparada desde Decision Engine.',
            'Score '.((int) ($decision['decision_score'] ?? 0)).'/100;',
            'banda '.((string) ($decision['decision_band_label'] ?? 'sin banda')).';',
            ((string) ($decision['evidence_quality_label'] ?? 'Evidencia limitada')).'.',
            'Motivo: '.((string) ($decision['why_now'] ?? 'señal operativa vigente')).'.',
            'La propuesta queda pendiente; no se ejecutó ningún cambio.',
        ]);
    }

    private function authorizeOrganization(
        User $actor,
        int $organizationId,
    ): void {
        $allowed = DB::table('organization_user')
            ->where('user_id', $actor->id)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para delegar decisiones en este ámbito.',
            );
        }
    }
}
