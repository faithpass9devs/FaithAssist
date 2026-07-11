<?php

namespace App\Repositories\Security;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserRepository
{
    public function paginateVisibleUsers(User $editor, string $search, int $perPage = 10): LengthAwarePaginator
    {
        $scope = new UserScopeService($editor);

        return User::query()
            ->with(['profile', 'roles', 'diocese:id,name', 'deanery:id,name', 'church:id,name', 'chapel:id,name'])
            ->when(! $scope->isGlobal(), function ($q) use ($editor) {
                $q->where(function ($sub) use ($editor) {
                    if ($editor->diocese_id !== null) {
                        $sub->where('diocese_id', $editor->diocese_id);
                    }
                    if ($editor->deanery_id !== null) {
                        $sub->where('deanery_id', $editor->deanery_id);
                    }
                    if ($editor->church_id !== null) {
                        $sub->where('church_id', $editor->church_id);
                    }
                    if ($editor->chapel_id !== null) {
                        $sub->where('chapel_id', $editor->chapel_id);
                    }
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('profile', fn ($p) => $p->where('name', 'like', "%{$search}%")
                    ->orWhere('paterno', 'like', "%{$search}%")
                    ->orWhere('materno', 'like', "%{$search}%"))
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadUserFormRelations(User $user): User
    {
        return $user->loadMissing([
            'profile',
            'roles',
            'diocese:id,name',
            'deanery:id,name',
            'church:id,name',
            'chapel:id,name',
        ]);
    }

    public function activeDioceses(UserScopeService $scope): Collection
    {
        return Diocese::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->dioceseIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function activeDeaneries(UserScopeService $scope): Collection
    {
        return Deanery::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->deaneryIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'diocese_id']);
    }

    public function activeChurches(UserScopeService $scope): Collection
    {
        return Church::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'deanery_id']);
    }

    public function activeChapels(UserScopeService $scope): Collection
    {
        return Chapel::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->chapelIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'church_id']);
    }

    public function permissionsForEditor(User $editor): Collection
    {
        return Permission::query()
            ->whereIn('id', $editor->getAllPermissions()->pluck('id'))
            ->orderBy('module_key')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'module_key']);
    }

    public function allowedRoles(User $editor): Collection
    {
        $editorPermissionIds = $editor->getAllPermissions()->pluck('id');

        return Role::with('permissions:id')
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->filter(function (Role $role) use ($editorPermissionIds): bool {
                return $role->permissions->every(
                    fn ($p) => $editorPermissionIds->contains($p->id)
                );
            })
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('id')->values(),
            ])
            ->values();
    }

    public function mustRemainDirectPermissionIds(): Collection
    {
        return Permission::query()
            ->whereIn('name', ['comunidades.export'])
            ->pluck('id');
    }

    public function permissionsByIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Permission::whereIn('id', $ids->all())->get();
    }
}
