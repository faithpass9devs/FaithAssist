<?php

namespace App\Policies;

use App\Models\Regions\Community;
use App\Models\User;
use App\Services\UserScopeService;

class CommunityPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'comunidades';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Community $community): bool
    {
        if (! $this->can($user, 'show')) {
            return false;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->municipalityIds()->contains($community->municipality_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create') && $this->hasFullScope($user);
    }

    public function update(User $user, Community $community): bool
    {
        if (! $this->can($user, 'update')) {
            return false;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->municipalityIds()->contains($community->municipality_id);
    }

    public function delete(User $user, Community $community): bool
    {
        if (! $this->can($user, 'delete')) {
            return false;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->municipalityIds()->contains($community->municipality_id);
    }

    public function export(User $user): bool
    {
        return $this->can($user, 'export');
    }
}
