<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_order_id',
        'task_id',
        'execution_order_id',
        'sequence',
        'title',
        'description',
        'contractual_due_date',
        'delivered_date',
        'conformity_date',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'contractual_due_date' => 'date',
            'delivered_date' => 'date',
            'conformity_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function executionOrder(): BelongsTo
    {
        return $this->belongsTo(
            ServiceOrderExecutionOrder::class,
            'execution_order_id',
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getOperationalStatusLabelAttribute(): string
    {
        return $this->contractual_status_label;
    }

    public function getContractualStatusLabelAttribute(): string
    {
        if ($this->conformity_date) {
            return 'Conforme';
        }

        if ($this->delivered_date) {
            return 'Entregado';
        }

        if ($this->is_overdue) {
            return 'Vencido';
        }

        return 'Pendiente';
    }

    public function getTaskStatusLabelAttribute(): string
    {
        if (! $this->task) {
            return 'Sin tarea';
        }

        return Task::statusOptions()[$this->task->status]
            ?? ucfirst($this->task->status);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (
            ! $this->contractual_due_date
            || $this->conformity_date
            || in_array(
                $this->task?->status,
                ['completed', 'cancelled'],
                true,
            )
        ) {
            return false;
        }

        return $this->contractual_due_date->isBefore(
            now(config('app.timezone', 'America/Lima'))
                ->startOfDay(),
        );
    }
}
