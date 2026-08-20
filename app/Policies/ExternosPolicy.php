<?php

namespace App\Policies;

use App\Models\External\ExternalChild;
use App\Models\User;
use App\Services\UserScopeService;

class ExternosPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'externos';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, ExternalChild $child): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $child);
    }

    public function import(User $user, ExternalChild $child): bool
    {
        return $this->can($user, 'import') && $this->withinScope($user, $child);
    }

    public function importAll(User $user): bool
    {
        return $this->can($user, 'import_all');
    }

    private function withinScope(User $user, ExternalChild $child): bool
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($child->church_id)
            || $scope->communityIds()->contains($child->community_id);
    }
}
