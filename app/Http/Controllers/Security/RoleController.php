<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\RoleRequest;
use App\Services\Security\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Security/Roles/Index', $this->roles->indexData($search));
    }

    public function create(): Response
    {
        return Inertia::render('Security/Roles/Form', $this->roles->createFormData());
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->roles->createRole([
            'name' => $request->name,
            'description' => $request->description,
            'permissions' => $request->input('permissions', []),
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Security/Roles/Form', $this->roles->editFormData($role));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->updateRole($role, [
            'name' => $request->name,
            'description' => $request->description,
            'permissions' => $request->input('permissions', []),
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }
}
