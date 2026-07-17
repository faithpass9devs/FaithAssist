<?php

namespace App\Services\Security;

use App\Models\Module;
use App\Repositories\Security\PermissionRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(private readonly PermissionRepository $permissions) {}

    public function indexData(string $search): array
    {
        return [
            'permissions' => $this->permissions->paginateWithSearch($search),
            'modules' => $this->permissions->allModules(),
            'search' => $search,
        ];
    }

    public function createPermission(array $data): array
    {
        $permission = $this->permissions->create($data);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $this->permissionData($permission);
    }

    public function updatePermission(Permission $permission, array $data): array
    {
        $permission = $this->permissions->update($permission, $data);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $this->permissionData($permission);
    }

    public function deletePermission(Permission $permission): void
    {
        $this->permissions->delete($permission);
    }

    private function permissionData(Permission $permission): array
    {
        return $permission->only(['id', 'name', 'description', 'module_key']);
    }
}
