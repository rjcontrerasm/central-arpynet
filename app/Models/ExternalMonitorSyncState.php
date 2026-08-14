<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalMonitorSyncState extends Model
{
    protected $fillable = [
        'provider',
        'last_sync_at',
        'last_success_at',
        'last_error_at',
        'last_generated_at',
        'last_item_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'last_item_count' => 'integer',
        ];
    }
}
