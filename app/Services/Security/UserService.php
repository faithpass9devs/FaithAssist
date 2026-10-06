<?php

namespace App\Services\Security;

use App\Models\Lada;
use App\Models\Profile;
use App\Models\User;
use App\Repositories\Security\UserRepository;
use App\Services\UserScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    public function paginatedIndexData(User $editor, string $search): array
    {
        return [
            'users' => $this->users
                ->paginateVisibleUsers($editor, $search)
                ->through(fn (User $user) => $this->indexUserData($user)),
            'search' => $search,
        ];
    }

    public function createFormData(User $editor): array
    {
        return [
            'user' => null,
            ...$this->formOptions($editor),
            'selectedRole' => null,
            'selectedPermissions' => [],
            'selectedDiocese' => null,
            'selectedDeanery' => null,
            'selectedChurch' => null,
            'selectedChapel' => null,
            'editorScope' => $this->buildEditorScope($editor),
            'selectedCountryCode' => Lada::defaultCode(),
            'countryCodes' => Lada::options(),
        ];
    }

    public function editFormData(User $editor, User $user): array
    {
        $user = $this->users->loadUserFormRelations($user);
        $editorPermissionIds = $this->assignablePermissionIds($editor);
        $allPermissionIds = $user->getAllPermissions()->pluck('id');
        $directManualAttendancePermissionIds = $user->getDirectPermissions()
            ->filter(fn (Permission $permission): bool => str_starts_with($permission->name, 'asistencias_manuales.'))
            ->pluck('id');

        $selectedPermissionIds = $allPermissionIds
            ->diff(
                $user->getAllPermissions()
                    ->filter(fn (Permission $permission): bool => str_starts_with($permission->name, 'asistencias_manuales.'))
                    ->pluck('id')
            )
            ->merge($directManualAttendancePermissionIds)
            ->intersect($editorPermissionIds)
            ->values()
            ->toArray();

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'whatsapp_phone' => $this->localWhatsAppPhone($user->whatsapp_phone),
                'whatsapp_country_code' => $this->whatsappCountryCode($user->whatsapp_phone),
                'name' => $user->profile?->name ?? '',
                'paterno' => $user->profile?->paterno ?? '',
                'materno' => $user->profile?->materno ?? '',
                'photo_url' => $user->profile_photo_path,
                'initials' => $this->resolveInitials($user),
                'full_name' => $this->resolveFullName($user),
                'created_at' => $user->created_at?->format('d/m/Y'),
            ],
            ...$this->formOptions($editor),
            'selectedRole' => $user->roles->first()?->id,
            'selectedPermissions' => $selectedPermissionIds,
            'selectedDiocese' => $user->diocese_id,
            'selectedDeanery' => $user->deanery_id,
            'selectedChurch' => $user->church_id,
            'selectedChapel' => $user->chapel_id,
            'editorScope' => $this->buildEditorScope($editor),
            'selectedCountryCode' => $this->whatsappCountryCode($user->whatsapp_phone),
            'countryCodes' => Lada::options(),
        ];
    }

    public function createUser(User $editor, array $data): User
    {
        return DB::transaction(function () use ($editor, $data): User {
            [$dioceseId, $deaneryId, $churchId, $chapelId] = $this->resolveScope($editor, $data);

            $user = User::create([
                'name' => $this->fullNameFromData($data),
                'email' => $data['email'],
                'whatsapp_phone' => $this->normalizeWhatsAppPhone(
                    $data['whatsapp_phone'] ?? null,
                    $data['whatsapp_country_code'] ?? null
                ),
                'password' => Hash::make($data['password']),
                'must_change_password' => true,
                'diocese_id' => $dioceseId,
                'deanery_id' => $deaneryId,
                'church_id' => $churchId,
                'chapel_id' => $chapelId,
            ]);

            Profile::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'paterno' => $data['paterno'],
                'materno' => $data['materno'] ?? null,
            ]);

            $roleId = $this->allowedRoleIdForSubmittedPermissions($editor, $data['role_id'] ?? null, $data);

            if ($roleId) {
                $user->syncRoles([$roleId]);
            }

            $directPerms = $this->directPermissionsForCreate($editor, $user, $data);
            $user->syncPermissions($directPerms);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $user;
        });
    }

    public function updateUser(User $editor, User $user, array $data): User
    {
        return DB::transaction(function () use ($editor, $user, $data): User {
            [$dioceseId, $deaneryId, $churchId, $chapelId] = $this->resolveScope($editor, $data);

            $user->update([
                'email' => $data['email'],
                'whatsapp_phone' => $this->normalizeWhatsAppPhone(
                    $data['whatsapp_phone'] ?? null,
                    $data['whatsapp_country_code'] ?? null
                ),
                'name' => $this->fullNameFromData($data),
                'diocese_id' => $dioceseId,
                'deanery_id' => $deaneryId,
                'church_id' => $churchId,
                'chapel_id' => $chapelId,
            ]);

            if (! empty($data['password'])) {
                $user->update([
                    'password' => Hash::make($data['password']),
                    'must_change_password' => true,
                ]);
            }

            $profile = $user->profile ?? new Profile(['user_id' => $user->id]);
            $profile->fill([
                'name' => $data['name'],
                'paterno' => $data['paterno'],
                'materno' => $data['materno'] ?? null,
            ])->save();

            $roleId = $this->allowedRoleIdForSubmittedPermissions($editor, $data['role_id'] ?? null, $data);
            $user->syncRoles($roleId ? [$roleId] : collect());
            $user->unsetRelation('roles');

            $finalPerms = $this->directPermissionsForUpdate($editor, $user, $data);
            $user->syncPermissions($finalPerms);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $user;
        });
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    private function indexUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'photo_url' => $user->profile_photo_path,
            'initials' => $this->resolveInitials($user),
            'full_name' => $this->resolveFullName($user),
            'role' => $user->roles->first()?->name,
            'diocese' => $user->diocese?->name,
            'deanery' => $user->deanery?->name,
            'church' => $user->church?->name,
            'chapel' => $user->chapel?->name,
        ];
    }

    private function formOptions(User $editor): array
    {
        $scope = new UserScopeService($editor);

        return [
            'roles' => $this->users->allowedRoles($editor),
            'permissionGroups' => $this->groupedPermissions($editor),
            'dioceses' => $this->users->activeDioceses($scope),
            'deaneries' => $this->users->activeDeaneries($scope),
            'churches' => $this->users->activeChurches($scope),
            'chapels' => $this->users->activeChapels($scope),
        ];
    }

    private function groupedPermissions(User $editor): array
    {
        return $this->users
            ->permissionsForEditor($editor)
            ->groupBy('module_key')
            ->map(fn ($perms, $key) => [
                'key' => $key,
                'label' => $this->getModuleLabel($key),
                'permissions' => $perms->values(),
            ])
            ->values()
            ->toArray();
    }

    private function allowedRoleId(User $editor, mixed $roleId): mixed
    {
        $allowedRoleIds = $this->users->allowedRoles($editor)->pluck('id');

        return $roleId && $allowedRoleIds->contains($roleId)
            ? $roleId
            : null;
    }

    private function allowedRoleIdForSubmittedPermissions(User $editor, mixed $roleId, array $data): mixed
    {
        $roleId = $this->allowedRoleId($editor, $roleId);

        if (! $roleId || ! array_key_exists('permissions', $data)) {
            return $roleId;
        }

        $rolePermissionIds = Role::query()
            ->find($roleId)
            ?->permissions()
            ->pluck('id') ?? collect();

        if ($rolePermissionIds->isEmpty()) {
            return $roleId;
        }

        $safeIds = $this->safeSubmittedPermissionIds($editor, $data);

        return $rolePermissionIds->diff($safeIds)->isEmpty()
            ? $roleId
            : null;
    }

    private function directPermissionsForCreate(User $editor, User $user, array $data): Collection
    {
        $safeIds = $this->safeSubmittedPermissionIds($editor, $data);
        $rolePermissionIds = $user->getPermissionsViaRoles()->pluck('id');
        $mustRemainDirectIds = $this->users->mustRemainDirectPermissionIds();
        $manualAttendancePermissionIds = $this->users
            ->permissionsByIds($safeIds)
            ->filter(fn (Permission $permission): bool => str_starts_with($permission->name, 'asistencias_manuales.'))
            ->pluck('id');

        $directIds = $safeIds
            ->diff($rolePermissionIds)
            ->merge($safeIds->intersect($mustRemainDirectIds))
            ->merge($manualAttendancePermissionIds)
            ->unique();

        return $this->users->permissionsByIds($directIds);
    }

    private function directPermissionsForUpdate(User $editor, User $user, array $data): Collection
    {
        $editorPermissionIds = $this->assignablePermissionIds($editor);
        $rolePermissionIds = $user->getPermissionsViaRoles()->pluck('id');
        $mustRemainDirectIds = $this->users->mustRemainDirectPermissionIds();

        $preservedPerms = $user->getDirectPermissions()
            ->filter(fn (Permission $p) => ! $editorPermissionIds->contains($p->id));

        $safeIds = $this->safeSubmittedPermissionIds($editor, $data);
        $manualAttendancePermissionIds = $this->users
            ->permissionsByIds($safeIds)
            ->filter(fn (Permission $permission): bool => str_starts_with($permission->name, 'asistencias_manuales.'))
            ->pluck('id');

        $directIds = $safeIds
            ->diff($rolePermissionIds)
            ->merge($safeIds->intersect($mustRemainDirectIds))
            ->merge($manualAttendancePermissionIds)
            ->unique();

        return $preservedPerms
            ->merge($this->users->permissionsByIds($directIds))
            ->unique('id');
    }

    private function safeSubmittedPermissionIds(User $editor, array $data): Collection
    {
        $editorPermissionIds = $this->assignablePermissionIds($editor);
        $submittedIds = collect(array_filter((array) ($data['permissions'] ?? [])));

        return $submittedIds->intersect($editorPermissionIds);
    }

    private function assignablePermissionIds(User $editor): Collection
    {
        $permissionIds = $editor->getAllPermissions()->pluck('id');

        if ($editor->hasRole('Superadmin')) {
            $permissionIds = $permissionIds->merge(
                Permission::query()
                    ->whereIn('name', [
                        'estados.export',
                        'municipios.export',
                        'comunidades.export',
                        'children.export',
                        'reinscripciones.export',
                    ])
                    ->pluck('id')
            );
        }

        return $permissionIds->unique()->values();
    }

    /**
     * @return array{int|null, int|null, int|null, int|null} [diocese_id, deanery_id, church_id, chapel_id]
     */
    private function resolveScope(User $editor, array $data): array
    {
        $scope = new UserScopeService($editor);

        if (! $scope->isGlobal()) {
            if ($editor->church_id !== null && $editor->chapel_id === null) {
                $requestedChapelId = isset($data['chapel_id']) ? (int) $data['chapel_id'] : null;
                $allowedChapelIds = $scope->chapelIds();

                $chapelId = $requestedChapelId !== null && $allowedChapelIds->contains($requestedChapelId)
                    ? $requestedChapelId
                    : null;

                return [
                    $editor->diocese_id,
                    $editor->deanery_id,
                    $editor->church_id,
                    $chapelId,
                ];
            }

            return [
                $editor->diocese_id,
                $editor->deanery_id,
                $editor->church_id,
                $editor->chapel_id,
            ];
        }

        return [
            $data['diocese_id'] ?? null,
            $data['deanery_id'] ?? null,
            $data['church_id'] ?? null,
            $data['chapel_id'] ?? null,
        ];
    }

    private function buildEditorScope(User $editor): array
    {
        return [
            'diocese_id' => $editor->diocese_id,
            'deanery_id' => $editor->deanery_id,
            'church_id' => $editor->church_id,
            'chapel_id' => $editor->chapel_id,
        ];
    }

    private function getModuleLabel(string $key): string
    {
        return match ($key) {
            'regions' => 'Regiones',
            'ecclesiastes' => 'Eclesiasticos',
            'security' => 'Seguridad',
            'operation' => 'Operación',
            'catechism' => 'Catecismo',
            'masses' => 'Misas',
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

    private function fullNameFromData(array $data): string
    {
        return trim("{$data['name']} {$data['paterno']} ".($data['materno'] ?? ''));
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
