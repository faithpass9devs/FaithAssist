<?php

namespace App\Policies;

use App\Models\Masses\Weekend;
use App\Models\User;
use App\Services\UserScopeService;

class WeekendPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'weekends';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Weekend $weekend): bool
    {
        return $this->can($user, 'show') && $this->withinVisibleScope($user, $weekend);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create') && $this->canManageChurchWeekends($user, null);
    }

    public function update(User $user, Weekend $weekend): bool
    {
        return $this->can($user, 'update') && $this->canManageChurchWeekends($user, $weekend->church_id);
    }

    public function delete(User $user, Weekend $weekend): bool
    {
        return $this->can($user, 'delete') && $this->canManageChurchWeekends($user, $weekend->church_id);
    }

    private function withinVisibleScope(User $user, Weekend $weekend): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->churchIds()->contains($weekend->church_id);
    }

    private function canManageChurchWeekends(User $user, ?int $churchId): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($user->church_id === null || $user->chapel_id !== null) {
            return false;
        }

        return $churchId === null || (int) $user->church_id === $churchId;
    }
}
