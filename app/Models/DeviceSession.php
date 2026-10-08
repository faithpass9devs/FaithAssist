<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    protected $table = 'sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'device_name',
        'operating_system',
        'browser',
        'first_seen_at',
        'last_seen_at',
        'status',
        'browser_id',
        'hidden_at',
        'latitude',
        'longitude',
        'location_accuracy',
        'location_label',
        'location_updated_at',
        'device_model',
    ];

    protected function casts(): array
    {
        return [
            'last_activity' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function lastActivityDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::createFromTimestamp((int) $value) : null,
        );
    }
}
