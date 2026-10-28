<?php

namespace App\Policies;

use App\Models\Operation\Staff;
use App\Models\User;
use App\Services\UserScopeService;

class StaffPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'personal';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $staff);
    }

    public function create(User $user): bool
    {
        if (! $this->can($user, 'create')) {
            return false;
        }

        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal()
            || $scope->churchIds()->isNotEmpty()
            || $scope->communityIds()->isNotEmpty();
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $staff);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $staff);
    }

    private function withinScope(User $user, Staff $staff): bool
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($staff->church_id)
            || $scope->communityIds()->contains($staff->community_id);
    }
}
