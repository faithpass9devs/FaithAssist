<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\PermissionRequest;
use App\Services\Security\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
        $this->authorizeResource(Permission::class, 'permiso');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Security/Permissions/Index', $this->permissions->indexData($search));
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->permissions->createPermission($request->validated()),
            'message' => 'Permiso creado correctamente.',
        ], 201);
    }

    public function update(PermissionRequest $request, Permission $permiso): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->permissions->updatePermission($permiso, $request->validated()),
            'message' => 'Permiso actualizado correctamente.',
        ]);
    }

    public function destroy(Permission $permiso): JsonResponse
    {
        $this->permissions->deletePermission($permiso);

        return response()->json([
            'success' => true,
            'message' => 'Permiso eliminado correctamente.',
        ]);
    }
}
