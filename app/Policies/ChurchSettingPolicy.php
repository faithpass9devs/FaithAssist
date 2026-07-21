<?php

namespace App\Policies;

use App\Models\Ecclesiastes\Church;
use App\Models\User;
use App\Services\UserScopeService;

class ChurchSettingPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'configuraciones';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function update(User $user, Church $church): bool
    {
        if (! $this->can($user, 'update')) {
            return false;
        }

        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $user->chapel_id === null && $scope->churchIds()->contains($church->id);
    }
}
