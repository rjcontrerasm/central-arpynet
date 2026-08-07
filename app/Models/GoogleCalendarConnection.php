<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarConnection extends Model
{
    protected $fillable = [
        'user_id',
        'calendar_id',
        'calendar_summary',
        'token_data',
        'scopes',
        'connected_at',
        'token_expires_at',
        'last_sync_at',
        'last_error_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'token_data' => 'encrypted:array',
            'scopes' => 'array',
            'connected_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return filled($this->token_data);
    }
}
