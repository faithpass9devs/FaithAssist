<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'description',
    'icon',
    'sort_order',
    'is_active',
    'created_by',
    'updated_by',
])]
#[Hidden([
    'created_at',
    'updated_at',
])]
class SettingCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'category_id')->orderBy('sort_order');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
