<?php

namespace App\Models;

use App\Models\Concerns\LogsActivityTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'paterno',
    'materno',
    'user_id',
])]
#[Hidden([
    'created_at',
    'updated_at',
])]
class Profile extends Model
{
    use HasFactory, LogsActivityTrail;

    protected function casts(): array
    {
        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
