<?php

namespace App\Models\Catechism;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'child_import_batch_id',
    'line',
    'message',
])]
class ChildImportError extends Model
{
    protected $table = 'child_import_errors';

    protected function casts(): array
    {
        return [
            'line' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ChildImportBatch::class, 'child_import_batch_id');
    }
}
