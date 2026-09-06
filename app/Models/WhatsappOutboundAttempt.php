<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOutboundAttempt extends Model
{
    protected $fillable = [
        'purpose',
        'request_kind',
        'recipient_sha256',
        'status',
        'message_id',
        'error_code',
        'error_subcode',
        'http_status',
        'error_type',
        'error_message',
        'fbtrace_id',
    ];

    protected function casts(): array
    {
        return ['http_status' => 'integer'];
    }
}
