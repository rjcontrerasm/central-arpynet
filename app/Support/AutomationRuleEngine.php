<?php

namespace App\Support;

use App\Models\AutomationRule;
use App\Models\ObligationOccurrence;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AutomationRuleEngine
{
    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
    ) {
    }

    public function preview(
        AutomationRule $rule,
        ?CarbonImmutable $now = null,
    ): Collection {
        $this->catalog->validate(
            $rule->trigger_key,
            $rule->action_key,
            $rule->mode,
        );

        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        return match ($rule->trigger_key) {
            'task.overdue' => $this->overdueTasks($rule, $now),
            'task.stagnant' => $this->stagnantTasks($rule, $now),
            'service.conformity_ready' => $this->conformityReady($rule),
            'service.invoice_overdue' => $this->overdueInvoices($rule, $now),
            'obligation.due_soon' => $this->obligationsDueSoon($rule, $now),
            'waiting.followup_overdue' => $this->waitingOverdue($rule, $now),
        };
    }

    public function previewActive(
        ?CarbonImmutable $now = null,
    ): Collection {
        return AutomationRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(
                fn (AutomationRule $rule): array => [
                    'rule_id' => $rule->id,
                    'name' => $rule->name,
                    'mode' => $rule->mode,
                    'matches' => $this->preview($rule, $now)->count(),
                ],
            );
    }

    private function overdueTasks(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        return Task::query()
            ->where('organization_id', $rule->organization_id)
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday', 'waiting'],
            )
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->limit(100)
            ->get()
            ->map(
                fn (Task $task): array => $this->candidate(
                    $rule,
                    'task',
                    $task->id,
                    $task->title,
                    'Tarea vencida desde '.$task->due_at->format('d/m/Y H:i'),
                    $task->updated_at,
                ),
            );
    }

    private function stagnantTasks(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        return Task::query()
            ->where('organization_id', $rule->organization_id)
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday', 'waiting', 'delegated'],
            )
            ->limit(250)
            ->get()
            ->map(function (Task $task) use ($rule, $now): ?array {
                $signal = TaskOperationalSignals::evaluate($task, $now);

                if (! $signal['stagnant']) {
                    return null;
                }

                return $this->candidate(
                    $rule,
                    'task',
                    $task->id,
                    $task->title,
                    implode(' · ', $signal['reasons']),
                    $task->updated_at,
                );
            })
            ->filter()
            ->values();
    }

    private function conformityReady(
        AutomationRule $rule,
    ): Collection {
        return ServiceOrder::query()
            ->where('organization_id', $rule->organization_id)
            ->where('stage', 'conformity')
            ->whereNull('invoice_number')
            ->limit(100)
            ->get()
            ->map(
                fn (ServiceOrder $order): array => $this->candidate(
                    $rule,
                    'service_order',
                    $order->id,
                    $order->title,
                    'Servicio con conformidad y sin factura registrada.',
                    $order->updated_at,
                ),
            );
    }

    private function overdueInvoices(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        return ServiceOrder::query()
            ->where('organization_id', $rule->organization_id)
            ->whereNull('paid_date')
            ->whereNotNull('invoice_due_date')
            ->whereDate('invoice_due_date', '<', $now->toDateString())
            ->where(function ($query): void {
                $query
                    ->whereNotNull('invoice_number')
                    ->orWhereNotNull('invoice_date')
                    ->orWhere('invoice_amount', '>', 0);
            })
            ->limit(100)
            ->get()
            ->map(
                fn (ServiceOrder $order): array => $this->candidate(
                    $rule,
                    'service_order',
                    $order->id,
                    $order->title,
                    'Factura vencida el '.$order->invoice_due_date->format('d/m/Y'),
                    $order->updated_at,
                ),
            );
    }

    private function obligationsDueSoon(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        $config = $rule->trigger_config ?? [];
        $days = max(0, min(30, (int) ($config['days'] ?? 7)));
        $end = $now->startOfDay()->addDays($days);

        return ObligationOccurrence::query()
            ->with('obligation')
            ->where('organization_id', $rule->organization_id)
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $now->toDateString())
            ->whereDate('due_date', '<=', $end->toDateString())
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(
                fn (ObligationOccurrence $item): array => $this->candidate(
                    $rule,
                    'obligation_occurrence',
                    $item->id,
                    $item->obligation?->name ?: 'Vencimiento',
                    'Vence el '.$item->due_date->format('d/m/Y'),
                    $item->updated_at,
                ),
            );
    }

    private function waitingOverdue(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        return Task::query()
            ->where('organization_id', $rule->organization_id)
            ->where('status', 'waiting')
            ->whereNotNull('waiting_until')
            ->where('waiting_until', '<', $now)
            ->orderBy('waiting_until')
            ->limit(100)
            ->get()
            ->map(
                fn (Task $task): array => $this->candidate(
                    $rule,
                    'task',
                    $task->id,
                    $task->title,
                    'Seguimiento vencido el '.$task->waiting_until->format('d/m/Y H:i'),
                    $task->updated_at,
                ),
            );
    }

    private function candidate(
        AutomationRule $rule,
        string $subjectType,
        int $subjectId,
        string $title,
        string $reason,
        mixed $updatedAt,
    ): array {
        $fingerprint = hash(
            'sha256',
            json_encode(
                [
                    'rule' => $rule->id,
                    'trigger' => $rule->trigger_key,
                    'action' => $rule->action_key,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'updated_at' => $updatedAt?->toIso8601String(),
                    'trigger_config' => $rule->trigger_config ?? [],
                    'action_config' => $rule->action_config ?? [],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );

        return [
            'rule_id' => $rule->id,
            'organization_id' => $rule->organization_id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'reason' => $reason,
            'trigger_key' => $rule->trigger_key,
            'action_key' => $rule->action_key,
            'mode' => $rule->mode,
            'fingerprint' => $fingerprint,
        ];
    }
}
