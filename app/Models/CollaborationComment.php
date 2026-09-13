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
        return $query->whereIn(
            'organization_id',
            $user->activeOrganizationIds(),
        );
    }
}
