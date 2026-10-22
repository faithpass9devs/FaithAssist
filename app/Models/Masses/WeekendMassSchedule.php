<?php

namespace App\Models\Masses;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'church_id',
    'chapel_id',
    'day_of_week',
    'name',
    'starts_time',
    'ends_time',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class WeekendMassSchedule extends Model
{
    use LogsActivityTrail, SoftDeletes;

    public const SATURDAY = 'saturday';

    public const SUNDAY = 'sunday';

    public const DAYS = [self::SATURDAY, self::SUNDAY];

    protected $table = 'weekend_mass_schedules';

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function chapel(): BelongsTo
    {
        return $this->belongsTo(Chapel::class, 'chapel_id');
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
