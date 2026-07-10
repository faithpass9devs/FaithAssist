<?php

namespace App\Policies;

use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\User;
use App\Services\UserScopeService;

class MassAttendancePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'mass_attendance';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, MassAttendance $massAttendance): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $massAttendance);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function scan(User $user, Mass $mass): bool
    {
        return $this->can($user, 'scan') && $this->massWithinScope($user, $mass);
    }

    public function update(User $user, MassAttendance $massAttendance): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $massAttendance);
    }

    public function delete(User $user, MassAttendance $massAttendance): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $massAttendance);
    }

    private function withinScope(User $user, MassAttendance $massAttendance): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($user->chapel_id !== null) {
            return (int) $user->chapel_id === $massAttendance->chapel_id;
        }

        return $scope->churchIds()->contains($massAttendance->church_id);
    }

    private function massWithinScope(User $user, Mass $mass): bool
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
}
