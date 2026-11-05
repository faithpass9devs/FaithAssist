<?php

namespace App\Models\Masses;

use App\Models\Catechism\Child;
use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Church;
use App\Models\Operation\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'event_id',
    'attendee_type',
    'attendee_id',
    'attendee_code',
    'church_id',
    'check_in_at',
    'check_in_by',
    'check_out_at',
    'check_out_by',
    'status',
    'notes',
])]
class EventAttendance extends Model
{
    use HasFactory, LogsActivityTrail;

    protected $table = 'event_attendance';

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime:Y-m-d H:i:s',
            'check_out_at' => 'datetime:Y-m-d H:i:s',
            'status' => 'string',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function attendee(): MorphTo
    {
        return $this->morphTo();
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
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

    public function isChildAttendee(): bool
    {
        return $this->attendee_type === Child::class;
    }

    public function isStaffAttendee(): bool
    {
        return $this->attendee_type === Staff::class;
    }
}
