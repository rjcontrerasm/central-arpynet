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
        'subject_type',
        'subject_id',
        'subject_title',
        'action_key',
        'action_label',
        'risk',
        'effect',
        'proposed_changes',
        'rationale',
        'fingerprint',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
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
