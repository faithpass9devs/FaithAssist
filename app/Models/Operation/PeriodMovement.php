<?php

namespace App\Models\Operation;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'period_id',
    'church_id',
    'period_movement_type_id',
    'status',
    'start_date',
    'end_date',
    'notes',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
    'created_at',
    'updated_at',
])]
class PeriodMovement extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'period_movements';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => 'string',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function periodMovementType(): BelongsTo
    {
        return $this->belongsTo(PeriodMovementType::class, 'period_movement_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
