<?php

namespace App\Models\Catechism;

use App\Models\Ecclesiastes\Church;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_id',
    'user_id',
    'church_id',
    'period_id',
    'period_movement_id',
    'original_filename',
    'storage_path',
    'queue_batch_id',
    'total_jobs',
    'imported_count',
    'failed_count',
    'error_report_path',
])]
class ChildImportBatch extends Model
{
    protected $table = 'child_import_batches';

    protected function casts(): array
    {
        return [
            'total_jobs' => 'integer',
            'imported_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function periodMovement(): BelongsTo
    {
        return $this->belongsTo(PeriodMovement::class, 'period_movement_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ChildImportError::class, 'child_import_batch_id');
    }
}
