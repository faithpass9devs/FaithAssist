<?php

namespace App\Policies;

use App\Models\Masses\ManualAttendance;
use App\Models\User;

class ManualAttendancePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'asistencias_manuales';
    }

    private function canDirect(User $user, string $action): bool
    {
        return $user->getDirectPermissions()
            ->pluck('name')
            ->contains($this->permissionModule().'.'.$action);
    }

    public function viewAny(User $user): bool
    {
        return $this->canDirect($user, 'read');
    }

    public function create(User $user): bool
    {
        return $this->canDirect($user, 'create');
    }

    public function update(User $user, ManualAttendance $manualAttendance): bool
    {
        return $this->canDirect($user, 'update');
    }

    public function delete(User $user, ManualAttendance $manualAttendance): bool
    {
        return $this->canDirect($user, 'delete');
    }
}
