<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'child_period_id',
    'level_id',
    'is_primary',
    'status',
])]
class ExternalChildPeriodLevel extends Model
{
    protected $connection = 'hostinger';

    protected $table = 'child_period_level';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'status' => 'string',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ExternalLevel::class, 'level_id');
    }
}
