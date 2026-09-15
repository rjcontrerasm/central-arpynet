<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Compatibilidad transitoria. La fuente de verdad es client_organization.
        'organization_id',
        'name',
        'legal_name',
        'tax_id',
        'contact_name',
        'email',
        'phone',
        'drive_url',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            $client->created_by ??= auth()->id();
        });

        static::created(function (Client $client): void {
            $client->syncLegacyOrganizationLink();
        });

        static::updated(function (Client $client): void {
            if ($client->wasChanged('organization_id')) {
                $client->syncLegacyOrganizationLink();
            }
        });
    }

    /**
     * Relación maestra compartida: un cliente puede ser atendido por varias
     * empresas/ámbitos sin duplicar su ficha.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['is_active', 'created_by'])
            ->withTimestamps();
    }

    /**
     * Relación legado mantenida temporalmente para compatibilidad.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(
        Builder $query,
        int $organizationId,
    ): Builder {
        return $query->whereHas(
            'organizations',
            fn (Builder $organizationQuery): Builder =>
                $organizationQuery
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('client_organization.is_active', true),
        );
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'organizations',
            fn (Builder $organizationQuery): Builder =>
                $organizationQuery
                    ->where('organizations.is_active', true)
                    ->where('client_organization.is_active', true)
                    ->whereHas(
                        'users',
                        fn (Builder $membershipQuery): Builder =>
                            $membershipQuery
                                ->where('users.id', $user->id)
                                ->where('organization_user.is_active', true),
                    ),
        );
    }

    public function isLinkedToOrganization(int $organizationId): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organizationId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function syncLegacyOrganizationLink(): void
    {
        if (
            ! $this->organization_id
            || ! Schema::hasTable('client_organization')
        ) {
            return;
        }

        $this->organizations()->syncWithoutDetaching([
            (int) $this->organization_id => [
                'is_active' => true,
                'created_by' => $this->created_by,
            ],
        ]);
    }
}
