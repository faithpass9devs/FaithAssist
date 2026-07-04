<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UserRequest;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Lada;
use App\Models\Profile;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $editor = $request->user();
        $scope = new UserScopeService($editor);

        $users = User::query()
            ->with(['profile', 'roles', 'diocese:id,name', 'deanery:id,name', 'church:id,name'])
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
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('profile', fn ($p) => $p->where('name', 'like', "%{$search}%")
                    ->orWhere('paterno', 'like', "%{$search}%")
                    ->orWhere('materno', 'like', "%{$search}%"))
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'photo_url' => $user->profile_photo_path,
                'initials' => $this->resolveInitials($user),
                'full_name' => $this->resolveFullName($user),
                'role' => $user->roles->first()?->name,
                'diocese' => $user->diocese?->name,
                'deanery' => $user->deanery?->name,
                'church' => $user->church?->name,
            ]);

        return Inertia::render('Security/Users/Index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        $editor = auth()->user();

        return Inertia::render('Security/Users/Form', [
            'user' => null,
            ...$this->formOptions(),
            'selectedRole' => null,
            'selectedPermissions' => [],
            'selectedDiocese' => null,
            'selectedDeanery' => null,
            'selectedChurch' => null,
            'editorScope' => $this->buildEditorScope($editor),
            'selectedCountryCode' => Lada::defaultCode(),
            'countryCodes' => $this->getCountryCodes(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $editor = $request->user();
        [$dioceseId, $deaneryId, $churchId] = $this->resolveScope($editor, $request);

        $user = User::create([
            'name' => trim("{$request->name} {$request->paterno} ".($request->materno ?? '')),
            'email' => $request->email,
            'whatsapp_phone' => $this->normalizeWhatsAppPhone(
                $request->input('whatsapp_phone'),
                $request->input('whatsapp_country_code')
            ),
            'password' => Hash::make($request->password),
            'diocese_id' => $dioceseId,
            'deanery_id' => $deaneryId,
            'church_id' => $churchId,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'paterno' => $request->paterno,
            'materno' => $request->materno,
        ]);

        $allowedRoleIds = $this->getAllowedRoles($editor)->pluck('id');
        $roleId = $request->role_id && $allowedRoleIds->contains($request->role_id)
            ? $request->role_id
            : null;

        if ($roleId) {
            $user->syncRoles([$roleId]);
        }

        $manageablePermissionIds = $editor->getAllPermissions()->pluck('id');
        if ($editor->hasRole('Superadmin')) {
            $manageablePermissionIds = $manageablePermissionIds->merge($this->mustRemainDirectPermissionIds());
        }
        $manageablePermissionIds = $manageablePermissionIds->unique();
        $submittedIds = collect(array_filter((array) $request->input('permissions', [])));
        $safeIds = $submittedIds->intersect($manageablePermissionIds);
        $rolePermissionIds = $user->getPermissionsViaRoles()->pluck('id');
        $mustRemainDirectIds = $this->mustRemainDirectPermissionIds();

        $directIds = $safeIds
            ->diff($rolePermissionIds)
            ->merge($safeIds->intersect($mustRemainDirectIds))
            ->unique();

        $directPerms = $directIds->isNotEmpty()
            ? Permission::whereIn('id', $directIds->all())->get()
            : collect();

        $user->syncPermissions($directPerms);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): Response
    {
        $usuario->loadMissing([
            'profile',
            'roles',
            'diocese:id,name',
            'deanery:id,name',
            'church:id,name',
        ]);

        $editor = auth()->user();
        $manageablePermissionIds = $editor->getAllPermissions()
            ->pluck('id')
            ->merge($this->mustRemainDirectPermissionIds())
            ->unique();

        return Inertia::render('Security/Users/Form', [
            'user' => [
                'id' => $usuario->id,
                'email' => $usuario->email,
                'whatsapp_phone' => $this->localWhatsAppPhone($usuario->whatsapp_phone),
                'whatsapp_country_code' => $this->whatsappCountryCode($usuario->whatsapp_phone),
                'name' => $usuario->profile?->name ?? '',
                'paterno' => $usuario->profile?->paterno ?? '',
                'materno' => $usuario->profile?->materno ?? '',
                'photo_url' => $usuario->profile_photo_path,
                'initials' => $this->resolveInitials($usuario),
                'full_name' => $this->resolveFullName($usuario),
                'created_at' => $usuario->created_at?->format('d/m/Y'),
            ],
            ...$this->formOptions(),
            'selectedRole' => $usuario->roles->first()?->id,
            'selectedPermissions' => $usuario->getAllPermissions()
                ->pluck('id')
                ->intersect($manageablePermissionIds)
                ->values()
                ->toArray(),
            'selectedDiocese' => $usuario->diocese_id,
            'selectedDeanery' => $usuario->deanery_id,
            'selectedChurch' => $usuario->church_id,
            'editorScope' => $this->buildEditorScope($editor),
            'selectedCountryCode' => $this->whatsappCountryCode($usuario->whatsapp_phone),
            'countryCodes' => $this->getCountryCodes(),
        ]);
    }

    public function update(UserRequest $request, User $usuario): RedirectResponse
    {
        $editor = $request->user();
        [$dioceseId, $deaneryId, $churchId] = $this->resolveScope($editor, $request);

        $usuario->update([
            'email' => $request->email,
            'whatsapp_phone' => $this->normalizeWhatsAppPhone(
                $request->input('whatsapp_phone'),
                $request->input('whatsapp_country_code')
            ),
            'name' => trim("{$request->name} {$request->paterno} ".($request->materno ?? '')),
            'diocese_id' => $dioceseId,
            'deanery_id' => $deaneryId,
            'church_id' => $churchId,
        ]);

        if ($request->filled('password')) {
            $usuario->update(['password' => Hash::make($request->password)]);
        }

        $profile = $usuario->profile ?? new Profile(['user_id' => $usuario->id]);
        $profile->fill([
            'name' => $request->name,
            'paterno' => $request->paterno,
            'materno' => $request->materno,
        ])->save();

        $allowedRoleIds = $this->getAllowedRoles($editor)->pluck('id');
        $roleId = $request->role_id && $allowedRoleIds->contains($request->role_id)
            ? $request->role_id
            : null;

        $usuario->syncRoles($roleId ? [$roleId] : collect());

        $manageablePermissionIds = $editor->getAllPermissions()->pluck('id');
        if ($editor->hasRole('Superadmin')) {
            $manageablePermissionIds = $manageablePermissionIds->merge($this->mustRemainDirectPermissionIds());
        }
        $manageablePermissionIds = $manageablePermissionIds->unique();
        $rolePermissionIds = $usuario->getPermissionsViaRoles()->pluck('id');
        $mustRemainDirectIds = $this->mustRemainDirectPermissionIds();

        // Preserve direct permissions the target has that the editor cannot manage.
        $preservedPerms = $usuario->getDirectPermissions()
            ->filter(fn (Permission $p) => ! $manageablePermissionIds->contains($p->id));

        // Sync submitted direct permissions that are within editor scope.
        $submittedIds = collect(array_filter((array) $request->input('permissions', [])));
        $safeIds = $submittedIds->intersect($manageablePermissionIds);
        $directIds = $safeIds
            ->diff($rolePermissionIds)
            ->merge($safeIds->intersect($mustRemainDirectIds))
            ->unique();

        $grantedPerms = $directIds->isNotEmpty()
            ? Permission::whereIn('id', $directIds->all())->get()
            : collect();

        $finalPerms = $preservedPerms->merge($grantedPerms)->unique('id');
        $usuario->syncPermissions($finalPerms);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        abort_unless($request->user()->can('usuarios.delete'), 403);

        if ($request->user()->id === $usuario->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario.',
            ], 422);
        }

        $usuario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    private function mustRemainDirectPermissionIds(): \Illuminate\Support\Collection
    {
        return Permission::query()
            ->whereIn('name', [
                'estados.export',
                'municipios.export',
                'comunidades.export',
                'children.export',
                'reinscripciones.export',
            ])
            ->pluck('id');
    }

    /**
     * Resolve scope FKs for store/update.
     * If editor has a restricted scope, force the new user into that same scope.
     *
     * @return array{int|null, int|null, int|null} [diocese_id, deanery_id, church_id]
     */
    private function resolveScope(User $editor, Request $request): array
    {
        $scope = new UserScopeService($editor);

        if (! $scope->isGlobal()) {
            return [
                $editor->diocese_id,
                $editor->deanery_id,
                $editor->church_id,
            ];
        }

        return [
            $request->input('diocese_id'),
            $request->input('deanery_id'),
            $request->input('church_id'),
        ];
    }

    private function buildEditorScope(User $editor): array
    {
        return [
            'diocese_id' => $editor->diocese_id,
            'deanery_id' => $editor->deanery_id,
            'church_id' => $editor->church_id,
        ];
    }

    private function getGroupedPermissions(User $editor): array
    {
        $manageablePermissionIds = $editor->getAllPermissions()->pluck('id');
        if ($editor->hasRole('Superadmin')) {
            $manageablePermissionIds = $manageablePermissionIds->merge($this->mustRemainDirectPermissionIds());
        }
        $manageablePermissionIds = $manageablePermissionIds->unique();

        return Permission::query()
            ->whereIn('id', $manageablePermissionIds)
            ->orderBy('module_key')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'module_key'])
            ->groupBy('module_key')
            ->map(fn ($perms, $key) => [
                'key' => $key,
                'label' => $this->getModuleLabel($key),
                'permissions' => $perms->values(),
            ])
            ->values()
            ->toArray();
    }

    private function getAllowedRoles(User $editor): Collection
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

    private function formOptions(): array
    {
        $editor = auth()->user();
        $scope = new UserScopeService($editor);

        $dioceses = Diocese::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->dioceseIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $deaneries = Deanery::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->deaneryIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'diocese_id']);

        $churches = Church::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'deanery_id']);

        return [
            'roles' => $this->getAllowedRoles($editor),
            'permissionGroups' => $this->getGroupedPermissions($editor),
            'dioceses' => $dioceses,
            'deaneries' => $deaneries,
            'churches' => $churches,
        ];
    }

    private function getCountryCodes(): array
    {
        return Lada::options();
    }

    private function getModuleLabel(string $key): string
    {
        return match ($key) {
            'regions' => 'Regiones',
            'ecclesiastes' => 'Eclesiasticos',
            'security' => 'Seguridad',
            'whatsapp' => 'WhatsApp',
            'operation' => 'Operación',
            'catechism' => 'Catecismo',
            default => ucfirst($key),
        };
    }

    private function resolveFullName(User $user): string
    {
        $p = $user->profile;
        if ($p) {
            return trim(collect([$p->name, $p->paterno, $p->materno])->filter()->implode(' '));
        }

        return $user->name;
    }

    private function resolveInitials(User $user): string
    {
        $p = $user->profile;
        if ($p && $p->name && $p->paterno) {
            return mb_strtoupper(mb_substr($p->name, 0, 1).mb_substr($p->paterno, 0, 1));
        }
        $chunks = preg_split('/\s+/', trim($user->name)) ?: [];
        if (count($chunks) >= 2) {
            return mb_strtoupper(mb_substr($chunks[0], 0, 1).mb_substr($chunks[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($chunks[0] ?? 'U', 0, 1));
    }

    private function normalizeWhatsAppPhone(?string $phone, ?string $countryCode = null): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $clean = preg_replace('/\D/', '', $phone) ?? '';

        if ($clean === '') {
            return null;
        }

        $countryCode = preg_replace('/\D/', '', (string) ($countryCode ?: Lada::defaultCode())) ?: Lada::defaultCode();

        if (strlen($clean) === 10) {
            return '+'.$countryCode.$clean;
        }

        if (str_starts_with($clean, $countryCode) && strlen($clean) > 10) {
            return '+'.$clean;
        }

        return '+'.$clean;
    }

    private function localWhatsAppPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $countryCode = $this->whatsappCountryCode($phone);
        $digits = preg_replace('/\D/', '', $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, $countryCode) && strlen($digits) > strlen($countryCode)) {
            return substr($digits, strlen($countryCode));
        }

        if (str_starts_with($digits, '52') && strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function whatsappCountryCode(?string $phone): string
    {
        return Lada::detectCountryCode($phone);
    }
}
