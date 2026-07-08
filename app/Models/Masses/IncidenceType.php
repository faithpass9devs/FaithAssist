<?php

namespace App\Models\Masses;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'description',
    'status',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class IncidenceType extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'incidence_types';

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'description' => 'string',
            'status' => 'string',
        ];
    }

    public function attendanceIncidents(): HasMany
    {
        return $this->hasMany(MassAttendanceIncident::class, 'incidence_type_id');
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
