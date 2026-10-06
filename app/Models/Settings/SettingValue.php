<?php

namespace App\Models\Settings;

use App\Models\Concerns\LogsActivityTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'setting_id',
    'scope',
    'scope_id',
    'value',
    'created_by',
    'updated_by',
])]
#[Hidden([
    'created_at',
    'updated_at',
])]
class SettingValue extends Model
{
    use LogsActivityTrail;

    public const SCOPE_DIOCESE = 'diocese';

    public const SCOPE_CHURCH = 'church';

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'scope_id' => 'integer',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'setting_id');
    }
}
