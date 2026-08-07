<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_obligation_id',
        'organization_id',
        'due_date',
        'status',
        'expected_amount',
        'actual_amount',
        'currency',
        'paid_date',
        'payment_reference',
        'receipt_url',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'paid_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ObligationOccurrence $occurrence): void {
            if (
                $occurrence->status === 'paid'
                && ! $occurrence->paid_date
            ) {
                $occurrence->paid_date = now()->toDateString();
            }

            if (
                in_array(
                    $occurrence->status,
                    ['paid', 'skipped'],
                    true,
                )
                && ! $occurrence->completed_at
            ) {
                $occurrence->completed_at = now();
            }

            if (
                ! in_array(
                    $occurrence->status,
                    ['paid', 'skipped'],
                    true,
                )
            ) {
                $occurrence->completed_at = null;
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
            'skipped' => 'No aplica',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(
            RecurringObligation::class,
            'recurring_obligation_id',
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'organization.users',
            fn (Builder $membershipQuery): Builder =>
                $membershipQuery
                    ->where('users.id', $user->id)
                    ->where(
                        'organization_user.is_active',
                        true,
                    ),
        );
    }

    public function getAttentionLabelAttribute(): string
    {
        if ($this->status === 'paid') {
            return 'Pagado';
        }

        if ($this->status === 'skipped') {
            return 'No aplica';
        }

        $reminderDays = $this->obligation
            ?->reminder_days_before ?? 7;

        if ($this->due_date->isPast()) {
            return 'Vencido';
        }

        if (
            $this->due_date->lessThanOrEqualTo(
                now()->addDays($reminderDays)->endOfDay(),
            )
        ) {
            return $this->obligation?->is_critical
                ? 'Próximo crítico'
                : 'Próximo';
        }

        return 'Programado';
    }

    public function getAttentionColorAttribute(): string
    {
        return match ($this->attention_label) {
            'Vencido',
            'Próximo crítico' => 'danger',
            'Próximo' => 'warning',
            'Pagado' => 'success',
            'No aplica' => 'gray',
            default => 'info',
        };
    }
}
