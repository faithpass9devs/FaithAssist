<?php

namespace App\Policies;

use App\Models\Masses\IncidenceType;
use App\Models\User;

class IncidenceTypePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'tipos_incidencias';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, IncidenceType $incidenceType): bool
    {
        return $this->can($user, 'show');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user, IncidenceType $incidenceType): bool
    {
        return $this->can($user, 'update');
    }

    public function delete(User $user, IncidenceType $incidenceType): bool
    {
        return $this->can($user, 'delete');
    }
}
