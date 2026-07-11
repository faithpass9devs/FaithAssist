<?php

namespace App\Models\Masses;

use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mass_id',
    'child_id',
    'child_code',
    'church_id',
    'chapel_id',
    'check_in_at',
    'check_in_by',
    'check_out_at',
    'check_out_by',
    'status',
    'notes',
])]
class MassAttendance extends Model
{
    use HasFactory;

    protected $table = 'mass_attendance';

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime:Y-m-d H:i:s',
            'check_out_at' => 'datetime:Y-m-d H:i:s',
            'status' => 'string',
        ];
    }

    public function mass(): BelongsTo
    {
        return $this->belongsTo(Mass::class, 'mass_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function chapel(): BelongsTo
    {
        return $this->belongsTo(Chapel::class, 'chapel_id');
    }

    public function checkInUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'check_in_by');
    }

    public function checkOutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'check_out_by');
    }

    public function isValidAttendance(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at !== null;
    }
}
