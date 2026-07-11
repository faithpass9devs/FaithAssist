<?php

namespace App\Policies;

use App\Models\Masses\MassAttendanceIncident;
use App\Models\User;
use App\Services\UserScopeService;

class MassAttendanceIncidentPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'incidencias_asistencia';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, MassAttendanceIncident $attendanceIncident): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $attendanceIncident);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user, MassAttendanceIncident $attendanceIncident): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $attendanceIncident);
    }

    public function delete(User $user, MassAttendanceIncident $attendanceIncident): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $attendanceIncident);
    }

    private function withinScope(User $user, MassAttendanceIncident $attendanceIncident): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($attendanceIncident->weekend?->church_id);
    }
}
