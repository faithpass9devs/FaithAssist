<?php

namespace App\Models\Settings;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'key',
    'name',
    'description',
    'status',
    'type_data',
    'meta',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
    'created_at',
    'updated_at',
])]
class SettingDefinition extends Model
{
    use LogsActivityTrail, SoftDeletes;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'status' => 'string',
            'type_data' => 'string',
        ];
    }

    public function churchSettings(): HasMany
    {
        return $this->hasMany(ChurchSetting::class);
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
