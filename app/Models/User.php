<?php

namespace App\Models;

use App\Models\Concerns\LogsActivityTrail;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'must_change_password',
    'profile_photo_path',
    'whatsapp_phone',
    'ui_theme',
    'ui_palette',
    'ui_custom_color',
    'diocese_id',
    'deanery_id',
    'church_id',
    'chapel_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivityTrail, Notifiable, SoftDeletes;

    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
        getAllPermissions as protected spatieGetAllPermissions;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'diocese_id', 'deanery_id', 'church_id', 'chapel_id', 'account_status', 'suspended_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_until' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function deanery(): BelongsTo
    {
        return $this->belongsTo(Deanery::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function chapel(): BelongsTo
    {
        return $this->belongsTo(Chapel::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Role permissions explicitly removed for this user while keeping the role.
     */
    public function revokedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_revoked_permissions')->withTimestamps();
    }

    public function revokedPermissionIds(): Collection
    {
        return $this->loadMissing('revokedPermissions')->revokedPermissions->pluck('id');
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $permission = $this->filterPermission($permission, $guardName);

        if ($this->revokedPermissionIds()->contains($permission->getKey())) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    public function getAllPermissions(): Collection
    {
        $revokedIds = $this->revokedPermissionIds();

        return $this->spatieGetAllPermissions()
            ->reject(fn (Permission $permission): bool => $revokedIds->contains($permission->getKey()))
            ->values();
    }

    /**
     * Permissions granted via roles or directly, ignoring per-user revocations.
     * Used to decide what the user can delegate, not what the user can do.
     */
    public function getGrantedPermissions(): Collection
    {
        return $this->spatieGetAllPermissions();
    }
}
