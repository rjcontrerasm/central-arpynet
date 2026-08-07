<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'description', 'type', 'horizon', 'status',
        'start_date', 'target_date', 'budget', 'currency', 'next_action',
        'blockers', 'notes', 'last_activity_at', 'created_by', 'is_private',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
            'budget' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'is_private' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            $project->created_by ??= auth()->id();
            $project->last_activity_at ??= now();
        });

        static::updating(function (Project $project): void {
            $fields = [
                'organization_id', 'name', 'description', 'type', 'horizon',
                'status', 'start_date', 'target_date', 'budget', 'currency',
                'next_action', 'blockers', 'notes', 'is_private',
            ];

            foreach ($fields as $field) {
                if ($project->isDirty($field)) {
                    $project->last_activity_at = now();
                    break;
                }
            }
        });
    }

    public static function typeOptions(): array
    {
        return [
            'project' => 'Proyecto',
            'goal' => 'Objetivo',
            'procedure' => 'Trámite',
            'trip' => 'Viaje',
            'purchase' => 'Compra',
            'idea' => 'Idea',
        ];
    }

    public static function horizonOptions(): array
    {
        return [
            'short' => 'Corto plazo',
            'medium' => 'Mediano plazo',
            'long' => 'Largo plazo',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'planned' => 'Planificado',
            'active' => 'En ejecución',
            'waiting' => 'En espera',
            'on_hold' => 'Pausado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'organization.users',
            fn (Builder $membershipQuery): Builder => $membershipQuery
                ->where('users.id', $user->id)
                ->where('organization_user.is_active', true),
        );
    }

    public function getProgressPercentAttribute(): int
    {
        $total = $this->tasks_count ?? $this->tasks()->count();

        if ($total === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        $completed = $this->completed_tasks_count
            ?? $this->tasks()->where('status', 'completed')->count();

        return (int) round(($completed / $total) * 100);
    }

    public function getStagnationDaysAttribute(): int
    {
        $activity = $this->last_activity_at ?? $this->updated_at ?? $this->created_at;
        return $activity ? $activity->diffInDays(now()) : 0;
    }

    public function getStagnationLabelAttribute(): string
    {
        return match (true) {
            $this->status === 'completed' => 'Completado',
            $this->status === 'cancelled' => 'Cancelado',
            $this->stagnation_days >= 30 => 'Estancado',
            $this->stagnation_days >= 15 => 'Revisar',
            default => 'En movimiento',
        };
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }
}
