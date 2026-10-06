<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'key',
    'name',
    'description',
    'type',
    'meta',
    'default_value',
    'sort_order',
    'is_active',
    'created_by',
    'updated_by',
])]
#[Hidden([
    'created_at',
    'updated_at',
])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'default_value' => 'json',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SettingCategory::class, 'category_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(SettingValue::class, 'setting_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
