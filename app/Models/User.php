<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_organization_id',
        'default_work_team_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function organizationRoleOptions(): array
    {
        return [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Miembro',
            'viewer' => 'Solo lectura',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'is_default', 'is_active'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps();
    }

    public function workTeams(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkTeam::class,
            'work_team_user',
        )
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function defaultWorkTeam(): BelongsTo
    {
        return $this->belongsTo(
            WorkTeam::class,
            'default_work_team_id',
        );
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'current_organization_id',
        );
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    public function assignedServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'assigned_to');
    }

    public function googleCalendarConnection(): HasOne
    {
        return $this->hasOne(GoogleCalendarConnection::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function activeOrganizationIds(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return $this->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->pluck('organizations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function taskScopeOrganizationIds(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return Task::query()
            ->visibleTo($this)
            ->select('organization_id')
            ->distinct()
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessTaskScopeOrganization(
        int $organizationId,
    ): bool {
        return in_array(
            $organizationId,
            array_values(array_unique(array_merge(
                $this->activeOrganizationIds(),
                $this->taskScopeOrganizationIds(),
            ))),
            true,
        );
    }

    public function writableOrganizationIds(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return $this->organizations()
            ->wherePivot('is_active', true)
            ->whereIn('organization_user.role', ['owner', 'admin', 'member'])
            ->where('organizations.is_active', true)
            ->pluck('organizations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function manageableOrganizationIds(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return $this->organizations()
            ->wherePivot('is_active', true)
            ->whereIn('organization_user.role', ['owner', 'admin'])
            ->where('organizations.is_active', true)
            ->pluck('organizations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessOrganization(int $organizationId): bool
    {
        return in_array(
            $organizationId,
            $this->activeOrganizationIds(),
            true,
        );
    }

    public function canWriteToOrganization(int $organizationId): bool
    {
        return in_array(
            $organizationId,
            $this->writableOrganizationIds(),
            true,
        );
    }

    public function canManageOrganization(int $organizationId): bool
    {
        return in_array(
            $organizationId,
            $this->manageableOrganizationIds(),
            true,
        );
    }

    public function transversalTaskScopeOrganizationIds(
        array $workTeamIds,
    ): array {
        if (! $this->is_active) {
            return [];
        }

        $teamIds = collect($workTeamIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($teamIds->isEmpty()) {
            return [];
        }

        $operationalTeamIds = DB::table(
            'work_team_user',
        )
            ->where('user_id', $this->id)
            ->whereIn('work_team_id', $teamIds)
            ->where('is_active', true)
            ->whereIn('role', ['lead', 'member'])
            ->pluck('work_team_id');

        if ($operationalTeamIds->isEmpty()) {
            return [];
        }

        return Task::query()
            ->whereHas(
                'workTeams',
                fn ($query) => $query
                    ->whereIn(
                        'work_teams.id',
                        $operationalTeamIds,
                    ),
            )
            ->whereHas(
                'organization',
                fn ($query) => $query
                    ->where('is_active', true),
            )
            ->select('organization_id')
            ->distinct()
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canCreateTransversalTeamTask(
        int $organizationId,
        array $workTeamIds,
    ): bool {
        return in_array(
            $organizationId,
            $this->transversalTaskScopeOrganizationIds(
                $workTeamIds,
            ),
            true,
        );
    }

    public function canAccessWorkTeam(int $workTeamId): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->workTeams()
            ->where('work_teams.id', $workTeamId)
            ->where('work_teams.is_active', true)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canManageTeam(): bool
    {
        return $this->manageableOrganizationIds() !== [];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin' || ! $this->is_active) {
            return false;
        }

        return $this->manageableOrganizationIds() !== [];
    }
}
