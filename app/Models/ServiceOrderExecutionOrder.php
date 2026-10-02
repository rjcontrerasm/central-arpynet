<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderExecutionOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_order_id',
        'fiscal_year',
        'document_type',
        'document_number',
        'document_url',
        'issued_date',
        'start_date',
        'end_date',
        'amount',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'issued_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Por emitir',
            'issued' => 'Emitida',
            'executing' => 'En ejecución',
            'closed' => 'Cerrada',
            'cancelled' => 'Cancelada',
        ];
    }

    public static function documentTypeOptions(): array
    {
        return [
            'service_order' => 'Orden de servicio',
            'purchase_order' => 'Orden de compra',
            'other' => 'Otro documento',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            ServiceOrderInvoice::class,
            'execution_order_id',
        );
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(
            ServiceOrderMilestone::class,
            'execution_order_id',
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
