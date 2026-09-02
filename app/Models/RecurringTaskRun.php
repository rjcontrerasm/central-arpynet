<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTaskRun extends Model
{
    protected $fillable = [
        'recurring_task_rule_id',
        'organization_id',
        'scheduled_for',
        'task_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(
            RecurringTaskRule::class,
            'recurring_task_rule_id',
        );
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(
            Task::class,
        );
    }
}
