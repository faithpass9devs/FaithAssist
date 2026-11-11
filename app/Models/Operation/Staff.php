<?php

namespace App\Models\Operation;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Church;
use App\Models\Regions\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_type_id',
    'church_id',
    'community_id',
    'name',
    'paterno',
    'materno',
    'phone_lada',
    'phone',
    'birthdate',
    'code',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden([
    'deleted_at',
])]
class Staff extends Model
{
    use HasFactory, LogsActivityTrail, SoftDeletes;

    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    public function staffType(): BelongsTo
    {
        return $this->belongsTo(StaffType::class, 'staff_type_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
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

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $terms = array_values(array_filter(preg_split('/\s+/', trim($search)) ?: []));

        if ($terms === []) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($terms): void {
            foreach ($terms as $term) {
                $builder->where(function (Builder $field) use ($term): void {
                    $field
                        ->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('paterno', 'like', "%{$term}%")
                        ->orWhere('materno', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhereHas('church', fn (Builder $church) => $church->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('community', fn (Builder $community) => $community->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('staffType', fn (Builder $type) => $type->where('name', 'like', "%{$term}%"));
                });
            }
        });
    }
}
