<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CollaborationComment extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'commentable_type',
        'commentable_id',
        'body',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'collaboration_comment_mentions',
        )->withTimestamps();
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->where(
            function (Builder $visibility) use ($user): void {
                $visibility
                    ->where(
                        function (Builder $tasks) use ($user): void {
                            $tasks
                                ->where(
                                    'commentable_type',
                                    Task::class,
                                )
                                ->whereIn(
                                    'commentable_id',
                                    Task::query()
                                        ->visibleTo($user)
                                        ->select('id'),
                                );
                        },
                    )
                    ->orWhere(
                        function (Builder $projects) use ($user): void {
                            $projects
                                ->where(
                                    'commentable_type',
                                    Project::class,
                                )
                                ->whereIn(
                                    'commentable_id',
                                    Project::query()
                                        ->visibleTo($user)
                                        ->select('id'),
                                );
                        },
                    )
                    ->orWhere(
                        function (Builder $services) use ($user): void {
                            $services
                                ->where(
                                    'commentable_type',
                                    ServiceOrder::class,
                                )
                                ->whereIn(
                                    'commentable_id',
                                    ServiceOrder::query()
                                        ->visibleTo($user)
                                        ->select('id'),
                                );
                        },
                    )
                    ->orWhere(
                        function (Builder $incidents) use ($user): void {
                            $incidents
                                ->where(
                                    'commentable_type',
                                    Incident::class,
                                )
                                ->whereIn(
                                    'commentable_id',
                                    Incident::query()
                                        ->visibleTo($user)
                                        ->select('id'),
                                );
                        },
                    );
            },
        );
    }
}
