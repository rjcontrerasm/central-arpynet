<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappInboundMessage extends Model
{
    protected $fillable = [
        'message_id',
        'sender_wa_id',
        'phone_number_id',
        'message_type',
        'status',
        'task_id',
        'text_sha256',
        'text_length',
        'received_at',
        'processed_at',
        'error_code',
        'confirmation_status',
        'confirmation_message_id',
        'confirmation_sent_at',
        'confirmation_error_code',
    ];

    protected function casts(): array
    {
        return [
            'text_length' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'confirmation_sent_at' =>
                'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
