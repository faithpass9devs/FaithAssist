<?php

namespace App\Repositories\Security;

use App\Models\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return Permission::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('module_key', 'like', "%{$search}%"))
            ->orderBy('module_key')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'module_key'])
            ->withQueryString();
    }

    public function allModules()
    {
        return Module::query()
            ->orderBy('name')
            ->get(['key', 'name']);
    }

    public function create(array $data): Permission
    {
        return Permission::create([
            ...$data,
            'guard_name' => 'web',
        ]);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        return $permission->fresh();
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
