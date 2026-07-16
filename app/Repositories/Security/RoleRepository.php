<?php

namespace App\Repositories\Security;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return Role::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getGroupedPermissions(): array
    {
        return Permission::query()
            ->orderBy('module_key')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'module_key'])
            ->groupBy('module_key')
            ->map(fn ($perms, $key) => [
                'key'         => $key,
                'label'       => $this->getModuleLabel($key),
                'permissions' => $perms->values(),
            ])
            ->values()
            ->toArray();
    }

    public function create(array $data): Role
    {
        return Role::create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'guard_name'  => 'web',
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'],
        ]);
        return $role->fresh();
    }

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->syncPermissions($permissionIds);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    private function getModuleLabel(string $key): string
    {
        return match ($key) {
            'regions'      => 'Regiones',
            'ecclesiastes' => 'Eclesiasticos',
            'catechism'    => 'Catecismo',
            'masses'       => 'Misas',
            'asistencias_manuales' => 'Asistencia manual',
            'security'     => 'Seguridad',
            'whatsapp'     => 'WhatsApp',
            'operation'    => 'Operación',
            default        => ucfirst($key),
        };
    }
}
