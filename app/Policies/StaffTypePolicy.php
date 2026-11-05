<?php

namespace App\Policies;

use App\Models\Operation\StaffType;
use App\Models\User;

class StaffTypePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'tipos_personal';
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Superadmin') && $this->can($user, 'read');
    }

    public function view(User $user, StaffType $staffType): bool
    {
        return $user->hasRole('Superadmin') && $this->can($user, 'show');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Superadmin') && $this->can($user, 'create');
    }

    public function update(User $user, StaffType $staffType): bool
    {
        return $user->hasRole('Superadmin') && $this->can($user, 'update');
    }

    public function delete(User $user, StaffType $staffType): bool
    {
        return $user->hasRole('Superadmin') && $this->can($user, 'delete');
    }
}
