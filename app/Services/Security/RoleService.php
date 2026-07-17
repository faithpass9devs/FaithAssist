<?php

namespace App\Services\Security;

use App\Repositories\Security\RoleRepository;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private readonly RoleRepository $roles) {}

    public function indexData(string $search): array
    {
        return [
            'roles' => $this->roles->paginateWithSearch($search),
            'search' => $search,
        ];
    }

    public function createFormData(): array
    {
        return [
            'role' => null,
            'permissionGroups' => $this->roles->getGroupedPermissions(),
            'selectedPermissions' => [],
        ];
    }

    public function editFormData(Role $role): array
    {
        return [
            'role' => $role->only(['id', 'name', 'description']),
            'permissionGroups' => $this->roles->getGroupedPermissions(),
            'selectedPermissions' => $role->permissions()->pluck('id')->toArray(),
        ];
    }

    public function createRole(array $data): Role
    {
        $role = $this->roles->create($data);
        $this->roles->syncPermissions($role, $data['permissions'] ?? []);
        return $role;
    }

    public function updateRole(Role $role, array $data): Role
    {
        $role = $this->roles->update($role, $data);
        $this->roles->syncPermissions($role, $data['permissions'] ?? []);
        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $this->roles->delete($role);
    }
}
