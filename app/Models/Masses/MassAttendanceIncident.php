<?php

namespace App\Models\Masses;

use App\Models\Catechism\Child;
use App\Models\Concerns\LogsActivityTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'weekend_id',
    'child_id',
    'incidence_type_id',
    'description',
    'status',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class MassAttendanceIncident extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'mass_attendance_incidents';

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function weekend(): BelongsTo
    {
        return $this->belongsTo(Weekend::class, 'weekend_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function incidenceType(): BelongsTo
    {
        return $this->belongsTo(IncidenceType::class, 'incidence_type_id');
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
