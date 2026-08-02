<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'church_id',
    'community_id',
    'name',
    'last_names',
    'birthdate',
    'sex',
    'email',
    'phone',
    'emergency_phone',
    'blood_type',
    'notes',
    'status',
])]
#[Hidden([
    'deleted_at',
])]
class ExternalChild extends Model
{
    use SoftDeletes;

    protected $connection = 'hostinger';

    protected $table = 'children';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'status' => 'string',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(ExternalCommunity::class, 'community_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(ExternalChurch::class, 'church_id');
    }

    public function childPeriods(): HasMany
    {
        return $this->hasMany(ExternalChildPeriod::class, 'child_id');
    }
}
