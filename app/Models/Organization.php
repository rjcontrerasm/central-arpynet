<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'legal_name',
        'tax_id',
        'color',
        'timezone',
        'is_active',
        'notes',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(
            function (Organization $organization): void {
                if (blank($organization->slug)) {
                    $organization->slug = Str::slug(
                        $organization->name,
                    );
                }

                if (
                    auth()->check()
                    && blank($organization->created_by)
                ) {
                    $organization->created_by = auth()->id();
                }
            },
        );

        static::created(
            function (Organization $organization): void {
                if (! auth()->check()) {
                    return;
                }

                $organization->users()->syncWithoutDetaching([
                    auth()->id() => [
                        'role' => 'owner',
                        'is_default' => false,
                        'is_active' => true,
                    ],
                ]);
            },
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_default', 'is_active'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
