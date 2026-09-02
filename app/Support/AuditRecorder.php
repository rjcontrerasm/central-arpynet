<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Organization;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditRecorder
{
    /**
     * Intentionally excludes notes, descriptions, URLs,
     * credentials, tokens and internal calculated fields.
     */
    private const SAFE_FIELDS = [
        'organization_id',
        'project_id',
        'client_id',
        'recurring_obligation_id',
        'title',
        'name',
        'legal_name',
        'tax_id',
        'category',
        'type',
        'horizon',
        'status',
        'stage',
        'urgency',
        'impact',
        'severity',
        'start_date',
        'target_date',
        'due_at',
        'due_date',
        'next_action',
        'next_action_at',
        'waiting_since',
        'waiting_until',
        'amount',
        'invoice_amount',
        'expected_amount',
        'actual_amount',
        'currency',
        'quotation_number',
        'quotation_date',
        'order_number',
        'order_received_date',
        'report_submitted_date',
        'conformity_date',
        'invoice_number',
        'invoice_date',
        'invoice_due_date',
        'paid_date',
        'closed_date',
        'frequency',
        'anchor_date',
        'end_date',
        'reminder_days_before',
        'is_critical',
        'is_active',
        'is_private',
        'provider',
        'reference',
        'payment_reference',
    ];

    public function record(
        Model $model,
        string $event,
    ): void {
        try {
            if (
                ! Schema::hasTable('audit_logs')
                || $model instanceof AuditLog
            ) {
                return;
            }

            $changes = $this->changesFor(
                $model,
                $event,
            );

            if (
                $event === 'updated'
                && empty($changes['fields'])
            ) {
                return;
            }

            AuditLog::query()->create([
                'organization_id' =>
                    $this->organizationId($model),
                'user_id' =>
                    auth()->id(),
                'event' =>
                    $event,
                'subject_type' =>
                    class_basename($model),
                'subject_id' =>
                    $model->getKey(),
                'subject_label' =>
                    $this->labelFor($model),
                'source' =>
                    app()->runningInConsole()
                        ? 'system'
                        : 'web',
                'changes' =>
                    $changes,
                'occurred_at' =>
                    now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function changesFor(
        Model $model,
        string $event,
    ): array {
        if ($event === 'created') {
            return [
                'new' => $this->safeSnapshot(
                    $model->getAttributes(),
                ),
            ];
        }

        if ($event === 'deleted') {
            return [
                'old' => $this->safeSnapshot(
                    $model->getAttributes(),
                ),
            ];
        }

        $changed = array_keys(
            $model->getChanges(),
        );

        $fields = [];

        foreach ($changed as $field) {
            if (! in_array(
                $field,
                self::SAFE_FIELDS,
                true,
            )) {
                continue;
            }

            $fields[$field] = [
                'old' => $this->normalize(
                    $model->getOriginal($field),
                ),
                'new' => $this->normalize(
                    $model->getAttribute($field),
                ),
            ];
        }

        return [
            'fields' => $fields,
        ];
    }

    private function safeSnapshot(
        array $attributes,
    ): array {
        $safe = [];

        foreach (self::SAFE_FIELDS as $field) {
            if (! array_key_exists(
                $field,
                $attributes,
            )) {
                continue;
            }

            if ($attributes[$field] === null) {
                continue;
            }

            $safe[$field] = $this->normalize(
                $attributes[$field],
            );
        }

        return $safe;
    }

    private function normalize(
        mixed $value,
    ): mixed {
        if ($value instanceof DateTimeInterface) {
            return $value->format(
                'Y-m-d H:i:s',
            );
        }

        if (
            is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
            || $value === null
        ) {
            return $value;
        }

        if (
            is_object($value)
            && method_exists($value, '__toString')
        ) {
            return (string) $value;
        }

        return null;
    }

    private function organizationId(
        Model $model,
    ): ?int {
        if ($model instanceof Organization) {
            return (int) $model->getKey();
        }

        $value = $model->getAttribute(
            'organization_id',
        );

        return $value !== null
            ? (int) $value
            : null;
    }

    private function labelFor(
        Model $model,
    ): string {
        foreach ([
            'title',
            'name',
            'reference',
            'invoice_number',
            'order_number',
        ] as $field) {
            $value = $model->getAttribute($field);

            if (
                is_string($value)
                && trim($value) !== ''
            ) {
                return mb_substr(
                    trim($value),
                    0,
                    255,
                );
            }
        }

        return class_basename($model)
            .' #'
            .$model->getKey();
    }
}
