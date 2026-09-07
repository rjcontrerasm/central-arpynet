<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentActionProposal extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'reviewed_by',
        'executed_by',
        'subject_type',
        'subject_id',
        'subject_title',
        'subject_version',
        'action_key',
        'action_label',
        'risk',
        'effect',
        'proposed_changes',
        'rationale',
        'fingerprint',
        'status',
        'reviewed_at',
        'executed_at',
        'execution_before',
        'execution_after',
        'undo_action_id',
    ];

    protected function casts(): array
    {
        return [
            'proposed_changes' => 'array',
            'reviewed_at' => 'datetime',
            'executed_at' => 'datetime',
            'execution_before' => 'array',
            'execution_after' => 'array',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'executed' => 'Ejecutada',
            'stale' => 'Desactualizada',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by',
        );
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'executed_by',
        );
    }

    public function undoAction(): BelongsTo
    {
        return $this->belongsTo(
            UndoAction::class,
            'undo_action_id',
        );
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'organization.users',
            fn (Builder $membership): Builder =>
                $membership
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
