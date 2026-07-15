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

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user, ManualAttendance $manualAttendance): bool
    {
        return $this->can($user, 'update');
    }

    public function delete(User $user, ManualAttendance $manualAttendance): bool
    {
        return $this->can($user, 'delete');
    }
}
