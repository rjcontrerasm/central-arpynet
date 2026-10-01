<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_order_id',
        'execution_order_id',
        'number',
        'issue_date',
        'due_date',
        'paid_date',
        'amount',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pendiente de emisión',
            'issued' => 'Emitida',
            'paid' => 'Pagada',
            'cancelled' => 'Anulada',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
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

    public function getDisplayStatusAttribute(): string
    {
        if (
            $this->status === 'issued'
            && ! $this->paid_date
            && $this->due_date
            && $this->due_date->isBefore(now()->startOfDay())
        ) {
            return 'Vencida';
        }

        return self::statusOptions()[$this->status]
            ?? ucfirst($this->status);
    }
}
