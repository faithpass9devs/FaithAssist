<?php

namespace App\Models\Ecclesiastes;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Regions\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'address',
    'community_id',
    'church_id',
    'status',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
    'created_at',
    'updated_at',
])]
class Chapel extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'chapels';

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

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'chapel_id');
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

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }
}
