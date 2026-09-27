<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'home_organization_id',
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function roleOptions(): array
    {
        return [
            'lead' => 'Líder',
            'member' => 'Miembro',
            'viewer' => 'Solo lectura',
        ];
    }

    public function homeOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'home_organization_id',
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'work_team_user',
        )
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withTimestamps();
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'users',
            fn (Builder $membership): Builder =>
                $membership
                    ->where('users.id', $user->id)
                    ->where('work_team_user.is_active', true),
        );
    }
}
