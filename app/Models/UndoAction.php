<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UndoAction extends Model
{
    protected $fillable = [
        'user_id',
        'action_type',
        'label',
        'entity_type',
        'entity_id',
        'payload',
        'return_url',
        'expires_at',
        'undone_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'undone_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }
}
