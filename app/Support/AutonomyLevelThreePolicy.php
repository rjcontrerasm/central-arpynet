<?php

namespace App\Support;

use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;

class AutonomyLevelThreePolicy
{
    public const DAILY_EXECUTION_LIMIT = 2;

    /**
     * Level 3 broadens autonomy to one bounded cross-module creation only:
     * create an internal collection task for an overdue invoiced service that
     * has no next action. The financial source remains untouched.
     */
    public function evaluate(
        array $decision,
        ServiceOrder $order,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $codes = collect($decision['reason_codes'] ?? [])
            ->filter(fn (mixed $code): bool => is_string($code))
            ->values()
            ->all();

        $hasInvoiceEvidence =
            filled($order->invoice_number)
            || $order->invoice_date !== null
            || (float) ($order->invoice_amount ?? 0) > 0;

        $invoiceOverdue =
            $order->invoice_due_date !== null
            && $order->invoice_due_date
                ->startOfDay()
                ->lt($now->startOfDay());

        $reasons = [];

        if (! in_array((string) ($decision['type'] ?? ''), ['service', 'service_order'], true)) {
            $reasons[] =
                'Autonomía L3 solo admite la señal de cobranza de un servicio.';
        }

        if ($order->stage !== 'invoiced') {
            $reasons[] =
                'Autonomía L3 exige que el servicio esté facturado.';
        }

        if ($order->paid_date !== null) {
            $reasons[] =
                'Autonomía L3 nunca crea cobranza para un servicio pagado.';
        }

        if (! $invoiceOverdue) {
            $reasons[] =
                'Autonomía L3 exige una factura vencida antes de hoy.';
        }

        if (! $hasInvoiceEvidence) {
            $reasons[] =
                'Autonomía L3 exige evidencia explícita de facturación.';
        }

        if (filled($order->next_action)) {
            $reasons[] =
                'Autonomía L3 solo interviene cuando no existe una siguiente acción registrada.';
        }

        if (($decision['level'] ?? null) !== 'critical') {
            $reasons[] =
                'Autonomía L3 exige una señal crítica.';
        }

        if (($decision['decision_band'] ?? null) !== 'immediate') {
            $reasons[] =
                'Autonomía L3 exige banda inmediata.';
        }

        if ((int) ($decision['decision_score'] ?? 0) < 95) {
            $reasons[] =
                'Autonomía L3 exige score de decisión mínimo 95.';
        }

        if (($decision['evidence_quality'] ?? null) !== 'high') {
            $reasons[] =
                'Autonomía L3 exige evidencia alta.';
        }

        foreach (['collection_risk', 'overdue', 'missing_next_action'] as $requiredCode) {
            if (! in_array($requiredCode, $codes, true)) {
                $reasons[] =
                    'Autonomía L3 exige la señal '.$requiredCode.'.';
            }
        }

        $eligible = $reasons === [];

        return [
            'eligible' => $eligible,
            'status' => $eligible
                ? 'bounded_cross_module_creation_allowed'
                : 'manual_only',
            'status_label' => $eligible
                ? 'Autonomía L3 disponible'
                : 'Revisión humana',
            'action' => $eligible
                ? 'create_collection_task'
                : null,
            'reasons' => $reasons,
            'requires_rule_opt_in' => true,
            'requires_exact_rule_owner' => true,
            'auto_execution' => true,
            'undo_required' => true,
            'source_mutation' => false,
            'daily_execution_limit' =>
                self::DAILY_EXECUTION_LIMIT,
            'allowed_subject_types' => ['service_order'],
            'allowed_subject_stages' => ['invoiced'],
            'allowed_actions' => ['create_collection_task'],
            'created_entity_types' => ['task'],
            'external_network' => false,
            'external_messages' => false,
            'deletes' => false,
            'bulk_execution' => false,
            'policy_version' => '2.33.1',
        ];
    }

    public function eligible(
        array $decision,
        ServiceOrder $order,
        ?CarbonImmutable $now = null,
    ): bool {
        return (bool) $this->evaluate(
            $decision,
            $order,
            $now,
        )['eligible'];
    }
}
