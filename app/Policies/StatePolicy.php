<?php

namespace App\Policies;

use App\Models\Regions\State;
use App\Models\User;

class StatePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'estados';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, State $state): bool
    {
        return $this->can($user, 'show');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user, State $state): bool
    {
        return $this->can($user, 'update');
    }

    public function delete(User $user, State $state): bool
    {
        return $this->can($user, 'delete');
    }

    public function export(User $user): bool
    {
        return $this->can($user, 'export');
    }
}
