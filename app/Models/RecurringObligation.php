<?php

namespace App\Models;

use App\Support\ObligationOccurrenceGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringObligation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'category',
        'description',
        'frequency',
        'anchor_date',
        'end_date',
        'expected_amount',
        'currency',
        'reminder_days_before',
        'is_critical',
        'is_active',
        'provider',
        'reference',
        'drive_url',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'anchor_date' => 'date',
            'end_date' => 'date',
            'expected_amount' => 'decimal:2',
            'reminder_days_before' => 'integer',
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RecurringObligation $obligation): void {
            $obligation->created_by ??= auth()->id();
        });

        static::saved(function (RecurringObligation $obligation): void {
            if (! $obligation->is_active) {
                return;
            }

            app(ObligationOccurrenceGenerator::class)
                ->generateFor(
                    $obligation->fresh(),
                    now()->startOfDay(),
                    now()->addDays(120)->endOfDay(),
                );
        });
    }

    public static function categoryOptions(): array
    {
        return [
            'service' => 'Servicio básico',
            'tax' => 'Impuesto / tributo',
            'property' => 'Predio / inmueble',
            'license' => 'Licencia',
            'domain' => 'Dominio / hosting',
            'report' => 'Informe / entregable',
            'insurance' => 'Seguro',
            'subscription' => 'Suscripción',
            'other' => 'Otro',
        ];
    }

    public static function frequencyOptions(): array
    {
        return [
            'monthly' => 'Mensual',
            'bimonthly' => 'Cada 2 meses',
            'quarterly' => 'Trimestral',
            'semiannual' => 'Semestral',
            'annual' => 'Anual',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ObligationOccurrence::class);
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
}
