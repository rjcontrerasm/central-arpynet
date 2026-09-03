<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReviewSession extends Model
{
    protected $fillable = [
        'user_id',
        'review_date',
        'decisions_reviewed_at',
        'waiting_reviewed_at',
        'tasks_reviewed_at',
        'operations_reviewed_at',
        'completed_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'decisions_reviewed_at' => 'datetime',
            'waiting_reviewed_at' => 'datetime',
            'tasks_reviewed_at' => 'datetime',
            'operations_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    public function reviewedCount(): int
    {
        return collect([
            $this->decisions_reviewed_at,
            $this->waiting_reviewed_at,
            $this->tasks_reviewed_at,
            $this->operations_reviewed_at,
        ])->filter()->count();
    }
}
