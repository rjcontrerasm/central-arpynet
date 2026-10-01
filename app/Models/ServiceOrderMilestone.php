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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getOperationalStatusLabelAttribute(): string
    {
        if ($this->conformity_date) {
            return 'Conforme';
        }

        if ($this->delivered_date) {
            return 'Entregado';
        }

        if ($this->task?->status === 'completed') {
            return 'Tarea completada';
        }

        if ($this->task?->status === 'cancelled') {
            return 'Cancelado';
        }

        if (
            $this->contractual_due_date
            && $this->contractual_due_date->isPast()
        ) {
            return 'Vencido';
        }

        return 'Pendiente';
    }
}
