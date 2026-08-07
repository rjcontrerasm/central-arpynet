<?php

namespace App\Models;

use App\Support\TaskPriorityCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'project_id',
        'parent_task_id',
        'title',
        'description',
        'next_action',
        'status',
        'urgency',
        'impact',
        'priority_score',
        'priority_band',
        'start_at',
        'due_at',
        'waiting_until',
        'completed_at',
        'last_activity_at',
        'waiting_for',
        'source',
        'external_system',
        'external_id',
        'assigned_to',
        'created_by',
        'is_private',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'priority_score' => 'integer',
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'waiting_until' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_private' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->created_by ??= auth()->id();
            $task->assigned_to ??= auth()->id();
            $task->last_activity_at ??= now();

            $task->synchronizeCompletionState();
            $task->refreshPriorityAttributes();
        });

        static::created(function (Task $task): void {
            $task->project?->touchActivity();
        });

        static::updating(function (Task $task): void {
            $activityFields = [
                'organization_id',
                'project_id',
                'parent_task_id',
                'title',
                'description',
                'next_action',
                'status',
                'urgency',
                'impact',
                'start_at',
                'due_at',
                'waiting_until',
                'waiting_for',
                'assigned_to',
                'is_private',
            ];

            foreach ($activityFields as $field) {
                if ($task->isDirty($field)) {
                    $task->last_activity_at = now();
                    break;
                }
            }

            $task->synchronizeCompletionState();
            $task->refreshPriorityAttributes();
        });

        static::updated(function (Task $task): void {
            $task->project?->touchActivity();

            if ($task->wasChanged('project_id') && $task->getOriginal('project_id')) {
                Project::query()->find($task->getOriginal('project_id'))?->touchActivity();
            }
        });

        static::deleted(function (Task $task): void {
            $task->project?->touchActivity();
        });
    }

    public static function statusOptions(): array
    {
        return [
            'inbox' => 'Bandeja de entrada',
            'pending' => 'Pendiente',
            'in_progress' => 'En curso',
            'waiting' => 'En espera',
            'delegated' => 'Delegada',
            'someday' => 'Algún día',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];
    }

    public static function urgencyOptions(): array
    {
        return [
            'low' => 'Baja',
            'normal' => 'Normal',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ];
    }

    public static function impactOptions(): array
    {
        return [
            'low' => 'Bajo',
            'normal' => 'Normal',
            'high' => 'Alto',
            'critical' => 'Crítico',
        ];
    }

    public static function priorityBandOptions(): array
    {
        return [
            'critical' => 'Crítico',
            'today' => 'Atender hoy',
            'week' => 'Esta semana',
            'planned' => 'Planificado',
            'waiting' => 'En espera',
            'delegated' => 'Delegada',
            'someday' => 'Algún día',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn(
            'status',
            ['completed', 'cancelled'],
        );
    }

    public function refreshPriorityAttributes(): void
    {
        $result = app(
            TaskPriorityCalculator::class,
        )->calculate($this);

        $this->priority_score = $result['score'];
        $this->priority_band = $result['band'];
    }

    private function synchronizeCompletionState(): void
    {
        if (
            $this->status === 'completed'
            && ! $this->completed_at
        ) {
            $this->completed_at = now();
        }

        if (
            $this->status !== 'completed'
            && $this->isDirty('status')
        ) {
            $this->completed_at = null;
        }
    }
}
