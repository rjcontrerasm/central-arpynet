<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEventLink extends Model
{
    protected $fillable = [
        'user_id',
        'calendar_id',
        'source_type',
        'source_id',
        'google_event_id',
        'content_hash',
        'last_synced_at',
        'last_error_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
