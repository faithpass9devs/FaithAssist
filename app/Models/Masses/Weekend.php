<?php

namespace App\Models\Masses;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'church_id',
    'name',
    'starts_at',
    'ends_at',
    'status',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class Weekend extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'weekends';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:Y-m-d H:i:s',
            'ends_at' => 'datetime:Y-m-d H:i:s',
            'status' => 'string',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function masses(): HasMany
    {
        return $this->hasMany(Mass::class, 'weekend_id');
    }

    public function attendanceIncidents(): HasMany
    {
        return $this->hasMany(MassAttendanceIncident::class, 'weekend_id');
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
