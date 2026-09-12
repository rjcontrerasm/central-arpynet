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

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_organization_id',
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

    public function googleCalendarConnection(): HasOne
    {
        return $this->hasOne(GoogleCalendarConnection::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
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

    public function canManageTeam(): bool
    {
        return $this->manageableOrganizationIds() !== [];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin' || ! $this->is_active) {
            return false;
        }

        return $this->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->exists();
    }
}
