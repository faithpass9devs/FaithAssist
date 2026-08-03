<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'child_id',
    'period_id',
    'church_id',
    'status',
    'primary_level_id',
])]
class ExternalChildPeriod extends Model
{
    protected $connection = 'hostinger';

    protected $table = 'child_periods';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(ExternalChildPeriodLevel::class, 'child_period_id');
    }
}
