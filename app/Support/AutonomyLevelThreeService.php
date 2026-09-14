<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutonomyLevelThreeService
{
    public const EXTERNAL_SYSTEM =
        'autonomy:service_invoice_collection';

    public function __construct(
        private readonly DecisionEngine $engine,
        private readonly AutonomyLevelThreePolicy $policy,
        private readonly GlobalUndoService $undo,
    ) {
    }

    /**
     * Execute the only L3 autonomous cross-module write: create one internal,
     * reversible collection task from a high-confidence overdue invoice signal.
     * The source service order is never mutated.
     */
    public function execute(
        User $actor,
        ServiceOrder $order,
        AutomationRule $rule,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        return DB::transaction(function () use (
            $actor,
            $order,
            $rule,
            $now,
        ): array {
            $locked = ServiceOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeActor(
                $actor,
                (int) $locked->organization_id,
            );

            $this->assertRuleContract(
                $actor,
                $locked,
                $rule,
            );

            $this->assertDailyLimit(
                (int) $locked->organization_id,
                $now,
            );

            $locked->loadMissing([
                'organization',
                'client',
            ]);

            $item = GlobalTrackingItemFactory::serviceOrder(
                $locked,
                $now,
            );

            $decision = collect(
                $this->engine->evaluate([$item])['decisions'] ?? [],
            )->first();

            if (! is_array($decision)) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'El servicio ya no produce una decisión activa. CENTRAL no creó ninguna tarea.',
                ]);
            }

            $autonomy = $this->policy->evaluate(
                $decision,
                $locked,
                $now,
            );

            if (! $autonomy['eligible']) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La decisión ya no cumple la política de Autonomía L3: '
                        .implode(' ', $autonomy['reasons']),
                ]);
            }

            $externalId = (string) $locked->id;

            if (
                Task::withTrashed()
                    ->where(
                        'organization_id',
                        $locked->organization_id,
                    )
                    ->where(
                        'external_system',
                        self::EXTERNAL_SYSTEM,
                    )
                    ->where('external_id', $externalId)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'Ya existe o existió una tarea autónoma de cobranza para este servicio. CENTRAL no la duplicó.',
                ]);
            }

            $task = Task::query()->create([
                'organization_id' =>
                    $locked->organization_id,
                'project_id' => null,
                'title' => mb_substr(
                    'Cobrar factura vencida: '.$locked->title,
                    0,
                    255,
                ),
                'description' => mb_substr(
                    $this->description(
                        $locked,
                        $decision,
                        $autonomy,
                        $rule,
                    ),
                    0,
                    2000,
                ),
                'status' => 'pending',
                'urgency' => 'high',
                'impact' => 'high',
                'due_at' => $now->endOfDay(),
                'source' => 'automation',
                'external_system' =>
                    self::EXTERNAL_SYSTEM,
                'external_id' => $externalId,
                'assigned_to' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $undoAction = $this->undo->rememberTaskCreated(
                $actor,
                $task,
                'Autonomía L3: tarea de cobranza creada',
                route(
                    'automation-center.index',
                    [],
                    false,
                ),
            );

            if (! $undoAction) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'Autonomía L3 exige reversibilidad y no pudo registrar una acción de deshacer. La tarea no fue creada.',
                ]);
            }

            AuditLog::query()->create([
                'organization_id' =>
                    $locked->organization_id,
                'user_id' => $actor->id,
                'event' => 'autonomy.level3.executed',
                'subject_type' => 'service_order',
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'source' =>
                    'central_autonomy_level_three',
                'changes' => [
                    'rule_id' => $rule->id,
                    'action' =>
                        'create_collection_task',
                    'created_task_id' => $task->id,
                    'decision_score' =>
                        (int) ($decision['decision_score'] ?? 0),
                    'policy_version' =>
                        $autonomy['policy_version'],
                    'undo_action_id' => $undoAction->id,
                    'source_mutated' => false,
                ],
                'occurred_at' => $now,
            ]);

            return [
                'ok' => true,
                'executed' => true,
                'task' => $task,
                'decision' => $decision,
                'autonomy' => $autonomy,
                'undo_action_id' => $undoAction->id,
                'message' =>
                    'Autonomía L3 creó una tarea interna de cobranza reversible sin modificar el servicio.',
            ];
        });
    }

    private function authorizeActor(
        User $actor,
        int $organizationId,
    ): void {
        if (
            ! $actor->is_active
            || ! $actor->canWriteToOrganization(
                $organizationId,
            )
        ) {
            throw new AuthorizationException(
                'Autonomía L3 exige que el propietario exacto de la regla siga activo y tenga permiso de escritura.',
            );
        }
    }

    private function assertRuleContract(
        User $actor,
        ServiceOrder $order,
        AutomationRule $rule,
    ): void {
        $valid =
            $rule->is_active
            && $rule->mode === 'automatic'
            && $rule->trigger_key
                === 'decision.level3_invoice_collection'
            && $rule->action_key
                === 'decision.create_collection_task'
            && (int) $rule->organization_id
                === (int) $order->organization_id
            && (int) $rule->created_by
                === (int) $actor->id;

        if (! $valid) {
            throw new AuthorizationException(
                'La regla no constituye un opt-in válido de Autonomía L3 para este actor y ámbito.',
            );
        }
    }

    private function assertDailyLimit(
        int $organizationId,
        CarbonImmutable $now,
    ): void {
        $used = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('event', 'autonomy.level3.executed')
            ->where(
                'occurred_at',
                '>=',
                $now->startOfDay(),
            )
            ->count();

        if ($used >= AutonomyLevelThreePolicy::DAILY_EXECUTION_LIMIT) {
            throw ValidationException::withMessages([
                'autonomy' =>
                    'Autonomía L3 alcanzó el límite diario de '
                    .AutonomyLevelThreePolicy::DAILY_EXECUTION_LIMIT
                    .' ejecuciones para esta organización.',
            ]);
        }
    }

    private function description(
        ServiceOrder $order,
        array $decision,
        array $autonomy,
        AutomationRule $rule,
    ): string {
        $invoice = filled($order->invoice_number)
            ? 'Factura '.$order->invoice_number.'.'
            : 'Factura registrada sin número explícito.';

        return implode(' ', [
            'Autonomía L3 '.$autonomy['policy_version'].' creó únicamente una tarea interna de cobranza; el servicio y sus datos financieros no fueron modificados.',
            $invoice,
            'Vencimiento: '.$order->invoice_due_date?->format('d/m/Y').'.',
            'Regla #'.$rule->id.' “'.$rule->name.'”.',
            'Decision Engine: score '.((int) ($decision['decision_score'] ?? 0)).'/100, evidencia alta y banda inmediata.',
            'Motivo: '.((string) ($decision['why_now'] ?? 'cobranza vencida sin siguiente acción')).'.',
        ]);
    }
}
