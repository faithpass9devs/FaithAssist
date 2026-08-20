<?php

namespace App\Policies;

use App\Models\Catechism\Child;
use App\Models\User;
use App\Services\UserScopeService;

class ChildPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'children';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Child $child): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $child);
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

    public function update(User $user, Child $child): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $child);
    }

    public function delete(User $user, Child $child): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $child);
    }

    public function export(User $user): bool
    {
        return $this->can($user, 'export');
    }

    public function massWhatsApp(User $user): bool
    {
        return $this->can($user, 'export');
    }

    private function withinScope(User $user, Child $child): bool
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($child->church_id)
            || $scope->communityIds()->contains($child->community_id);
    }
}
