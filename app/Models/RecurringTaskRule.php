<?php

namespace App\Models;

use App\Support\RecurringTaskGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTaskRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'next_action',
        'frequency',
        'anchor_date',
        'end_date',
        'create_days_before',
        'due_time',
        'urgency',
        'impact',
        'is_private',
        'is_active',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'anchor_date' => 'date',
            'end_date' => 'date',
            'create_days_before' =>
                'integer',
            'is_private' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                RecurringTaskRule $rule,
            ): void {
                $rule->created_by ??=
                    auth()->id();

                $rule->assigned_to ??=
                    auth()->id();
            },
        );

        static::saved(
            function (
                RecurringTaskRule $rule,
            ): void {
                if (
                    ! $rule->is_active
                ) {
                    return;
                }

                app(
                    RecurringTaskGenerator::class,
                )->generateFor(
                    $rule->fresh(),
                    now(),
                );
            },
        );
    }

    public static function frequencyOptions(): array
    {
        return [
            'daily' => 'Diaria',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            'bimonthly' => 'Cada 2 meses',
            'quarterly' => 'Trimestral',
            'semiannual' => 'Semestral',
            'annual' => 'Anual',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class,
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to',
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function runs(): HasMany
    {
        return $this->hasMany(
            RecurringTaskRun::class,
        );
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'organization.users',
            fn (
                Builder $membershipQuery,
            ): Builder =>
                $membershipQuery
                    ->where(
                        'users.id',
                        $user->id,
                    )
                    ->where(
                        'organization_user.is_active',
                        true,
                    ),
        );
    }
}
