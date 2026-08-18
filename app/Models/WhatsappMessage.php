<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'to_phone',
        'country_code',
        'message_type',
        'message_body',
        'pdf_path',
        'filename',
        'baileys_message_id',
        'status',
        'error_message',
        'retry_count',
        'max_retries',
        'batch_key',
        'legend_text',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];
}
