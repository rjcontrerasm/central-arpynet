<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleRun extends Model
{
    protected $fillable = [
        'automation_rule_id', 'organization_id', 'subject_type',
        'subject_id', 'fingerprint', 'outcome', 'payload',
        'evaluated_at', 'executed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'evaluated_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
