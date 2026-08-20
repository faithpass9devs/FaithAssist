<?php

namespace App\Models;

use App\Models\Catechism\Child;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'child_id',
    'batch_id',
    'error_message',
])]
class FailedWhatsappChild extends Model
{
    protected $table = 'failed_whatsapp_children';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsappMassBatch::class, 'batch_id');
    }
}
