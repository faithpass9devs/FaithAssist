<?php

namespace App\Policies;

use App\Models\Masses\Mass;
use App\Models\User;
use App\Services\UserScopeService;

class MassPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'masses';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Mass $mass): bool
    {
        return $this->can($user, 'show') && $this->withinVisibleScope($user, $mass);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create') && $this->hasMassLocationScope($user);
    }

    public function update(User $user, Mass $mass): bool
    {
        return $this->can($user, 'update') && $this->canManageMass($user, $mass);
    }

    public function delete(User $user, Mass $mass): bool
    {
        return $this->can($user, 'delete') && $this->canManageMass($user, $mass);
    }

    private function withinVisibleScope(User $user, Mass $mass): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($user->chapel_id !== null) {
            return (int) $user->chapel_id === $mass->chapel_id;
        }

        return $scope->churchIds()->contains($mass->church_id);
    }

    private function hasMassLocationScope(User $user): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $user->church_id !== null || $user->chapel_id !== null;
    }

    private function canManageMass(User $user, Mass $mass): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($user->chapel_id !== null) {
            return (int) $user->chapel_id === $mass->chapel_id;
        }

        return $user->church_id !== null
            && (int) $user->church_id === $mass->church_id
            && $mass->chapel_id === null;
    }
}
