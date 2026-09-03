<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReviewSession extends Model
{
    protected $fillable = [
        'user_id',
        'week_start',
        'carryover_reviewed_at',
        'stagnation_reviewed_at',
        'finance_reviewed_at',
        'obligations_reviewed_at',
        'horizon_reviewed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'carryover_reviewed_at' =>
                'datetime',
            'stagnation_reviewed_at' =>
                'datetime',
            'finance_reviewed_at' =>
                'datetime',
            'obligations_reviewed_at' =>
                'datetime',
            'horizon_reviewed_at' =>
                'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }
}
