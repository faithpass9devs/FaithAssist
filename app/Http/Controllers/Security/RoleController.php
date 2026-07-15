<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\RoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        $roles = Role::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Security/Roles/Index', [
            'roles'  => $roles,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Security/Roles/Form', [
            'role'                => null,
            'permissionGroups'    => $this->getGroupedPermissions(),
            'selectedPermissions' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name'        => $request->name,
            'description' => $request->description,
            'guard_name'  => 'web',
        ]);

        $role->syncPermissions($request->input('permissions', []));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Security/Roles/Form', [
            'role'                => $role->only(['id', 'name', 'description']),
            'permissionGroups'    => $this->getGroupedPermissions(),
            'selectedPermissions' => $role->permissions()->pluck('id')->toArray(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        $role->syncPermissions($request->input('permissions', []));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    private function getGroupedPermissions(): array
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
