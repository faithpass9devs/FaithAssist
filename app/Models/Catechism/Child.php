<?php

namespace App\Models\Catechism;

use App\Globals\Status;
use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'church_id',
    'community_id',
    'name',
    'paterno',
    'materno',
    'code',
    'origin',
    'birthdate',
    'sex',
    'email',
    'phone_lada',
    'phone',
    'emergency_phone_lada',
    'emergency_phone',
    'blood_type',
    'observations',
    'privacy_terms',
    'status',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class Child extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'children';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'privacy_terms' => 'boolean',
            'status' => 'string',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function levelAssignments(): HasMany
    {
        return $this->hasMany(ChildLevelAssignment::class, 'child_id');
    }

    public function activeLevelAssignments(): HasMany
    {
        return $this->levelAssignments()->where('status', Status::ACTIVE);
    }

    public function reinscriptions(): HasMany
    {
        return $this->hasMany(ChildReinscription::class, 'child_id');
    }

    public function massAttendances(): HasMany
    {
        return $this->hasMany(MassAttendance::class, 'child_id');
    }

    public function massAttendanceIncidents(): HasMany
    {
        return $this->hasMany(MassAttendanceIncident::class, 'child_id');
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(
            Level::class,
            'child_level_assignments',
            'child_id',
            'level_id'
        )
            ->withPivot(['id', 'period_id', 'period_movement_id', 'status', 'assigned_at', 'ended_at'])
            ->withTimestamps();
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', Status::ACTIVE);
    }
}
