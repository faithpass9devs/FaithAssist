<?php

namespace App\Models;

use App\Models\Catechism\Child;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'child_id',
    'whatsapp_mass_batch_id',
    'whatsapp_message_id',
    'status',
    'error_message',
    'sent_at',
])]
class ChildWhatsappDelivery extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'child_whatsapp_deliveries';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsappMassBatch::class, 'whatsapp_mass_batch_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }
}
