<?php

namespace App\Models\Masses;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Chapel;
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
    'weekend_id',
    'church_id',
    'chapel_id',
    'name',
    'starts_at',
    'ends_at',
    'status',
    'attendance_check_in_status',
    'attendance_check_out_status',
    'notes',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class Mass extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'masses';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:Y-m-d H:i',
            'ends_at' => 'datetime:Y-m-d H:i',
            'status' => 'string',
            'attendance_check_in_status' => 'string',
            'attendance_check_out_status' => 'string',
        ];
    }

    public function weekend(): BelongsTo
    {
        return $this->belongsTo(Weekend::class, 'weekend_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function chapel(): BelongsTo
    {
        return $this->belongsTo(Chapel::class, 'chapel_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MassAttendance::class, 'mass_id');
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
