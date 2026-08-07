<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'title',
        'description',
        'stage',
        'stage_changed_at',
        'quotation_number',
        'quotation_date',
        'order_number',
        'order_received_date',
        'start_date',
        'end_date',
        'report_submitted_date',
        'conformity_date',
        'invoice_number',
        'invoice_date',
        'invoice_due_date',
        'paid_date',
        'closed_date',
        'amount',
        'invoice_amount',
        'currency',
        'includes_tax',
        'next_action',
        'next_action_at',
        'drive_url',
        'notes',
        'last_activity_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage_changed_at' => 'datetime',
            'quotation_date' => 'date',
            'order_received_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'report_submitted_date' => 'date',
            'conformity_date' => 'date',
            'invoice_date' => 'date',
            'invoice_due_date' => 'date',
            'paid_date' => 'date',
            'closed_date' => 'date',
            'amount' => 'decimal:2',
            'invoice_amount' => 'decimal:2',
            'includes_tax' => 'boolean',
            'next_action_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceOrder $order): void {
            $order->created_by ??= auth()->id();
            $order->stage_changed_at ??= now();
            $order->last_activity_at ??= now();

            $order->validateClientOwnership();
        });

        static::updating(function (ServiceOrder $order): void {
            $order->validateClientOwnership();

            if ($order->isDirty('stage')) {
                $order->stage_changed_at = now();
            }

            $activityFields = [
                'organization_id',
                'client_id',
                'title',
                'description',
                'stage',
                'quotation_number',
                'quotation_date',
                'order_number',
                'order_received_date',
                'start_date',
                'end_date',
                'report_submitted_date',
                'conformity_date',
                'invoice_number',
                'invoice_date',
                'invoice_due_date',
                'paid_date',
                'closed_date',
                'amount',
                'invoice_amount',
                'currency',
                'includes_tax',
                'next_action',
                'next_action_at',
                'drive_url',
                'notes',
            ];

            foreach ($activityFields as $field) {
                if ($order->isDirty($field)) {
                    $order->last_activity_at = now();
                    break;
                }
            }
        });
    }

    public static function stageOptions(): array
    {
        return [
            'opportunity' => 'Oportunidad',
            'quotation' => 'Cotización',
            'order_received' => 'Orden recibida',
            'execution' => 'En ejecución',
            'report_submitted' => 'Informe presentado',
            'conformity' => 'Conformidad',
            'invoiced' => 'Facturado',
            'paid' => 'Pagado',
            'closed' => 'Cerrado',
            'cancelled' => 'Cancelado',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function getDaysInStageAttribute(): int
    {
        $from = $this->stage_changed_at
            ?? $this->created_at
            ?? now();

        return $from->diffInDays(now());
    }

    public function getAttentionLabelAttribute(): string
    {
        if (in_array(
            $this->stage,
            ['closed', 'cancelled'],
            true,
        )) {
            return 'Cerrado';
        }

        if (
            $this->stage === 'invoiced'
            && ! $this->paid_date
            && $this->invoice_due_date
            && $this->invoice_due_date->isPast()
        ) {
            return 'Cobranza vencida';
        }

        if (
            $this->next_action_at
            && $this->next_action_at->isPast()
        ) {
            return 'Seguimiento vencido';
        }

        if (
            in_array(
                $this->stage,
                ['opportunity', 'quotation'],
                true,
            )
            && $this->days_in_stage >= 7
        ) {
            return 'Sin seguimiento';
        }

        if (
            $this->stage === 'report_submitted'
            && $this->days_in_stage >= 5
        ) {
            return 'Esperar conformidad';
        }

        if (
            $this->stage === 'conformity'
            && $this->days_in_stage >= 2
        ) {
            return 'Facturar';
        }

        if ($this->days_in_stage >= 15) {
            return 'Sin movimiento';
        }

        return 'Al día';
    }

    public function getAttentionColorAttribute(): string
    {
        return match ($this->attention_label) {
            'Cobranza vencida',
            'Seguimiento vencido' => 'danger',

            'Sin seguimiento',
            'Esperar conformidad',
            'Facturar',
            'Sin movimiento' => 'warning',

            'Al día' => 'success',

            default => 'gray',
        };
    }

    private function validateClientOwnership(): void
    {
        if (! $this->client_id || ! $this->organization_id) {
            return;
        }

        $belongs = Client::query()
            ->whereKey($this->client_id)
            ->where('organization_id', $this->organization_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'client_id' =>
                    'El cliente no pertenece a la empresa seleccionada.',
            ]);
        }
    }
}
