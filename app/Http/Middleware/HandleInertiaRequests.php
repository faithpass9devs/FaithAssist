<?php

namespace App\Http\Middleware;

use App\Models\InternalNotification;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () use ($request): array {
                $authUser = $request->user()?->fresh();
                $resolvedPermissions = $authUser ? $this->resolvedAuthPermissions($authUser) : collect();

                return [
                    'user' => $this->buildAuthUserPayload($authUser),
                    'permissions' => $resolvedPermissions->values()->all(),
                    'direct_permissions' => $authUser?->getDirectPermissions()->pluck('name')->values()->all() ?? [],
                    'roles' => $authUser?->getRoleNames()->values()->all() ?? [],
                    'scope' => $this->buildScopePayload($authUser, $resolvedPermissions->all()),
                    'pending_moderation_notification' => $this->pendingModerationNotification($authUser),
                ];
            },
        ];
    }

    private function resolvedAuthPermissions(User $user)
    {
        $manualPrefix = 'asistencias_manuales.';

        $all = $user->getAllPermissions()->pluck('name');
        $directManual = $user->getDirectPermissions()
            ->pluck('name')
            ->filter(fn (string $permission): bool => str_starts_with($permission, $manualPrefix));

        return $all
            ->reject(fn (string $permission): bool => str_starts_with($permission, $manualPrefix))
            ->merge($directManual)
            ->unique()
            ->values();
    }

    private function buildAuthUserPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing([
            'profile',
            'diocese:id,name',
            'deanery:id,name',
            'church:id,name',
            'chapel:id,name',
        ]);

        $profileName = trim(collect([
            $user->profile?->name,
            $user->profile?->paterno,
            $user->profile?->materno,
        ])->filter()->implode(' '));

        $displayName = $profileName !== '' ? $profileName : $user->name;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $displayName,
            'initials' => $this->resolveInitials($user),
            'photo_url' => $user->profile_photo_path,
            'ui_theme' => $user->ui_theme,
            'ui_palette' => $user->ui_palette,
            'ui_custom_color' => $user->ui_custom_color,
            'account_status' => $user->account_status ?? 'active',
            'suspended_until' => $user->suspended_until?->toIso8601String(),
            'profile' => $user->profile ? [
                'name' => $user->profile->name,
                'paterno' => $user->profile->paterno,
                'materno' => $user->profile->materno,
            ] : null,
            'diocese' => $user->diocese ? [
                'id' => $user->diocese->id,
                'name' => $user->diocese->name,
            ] : null,
            'deanery' => $user->deanery ? [
                'id' => $user->deanery->id,
                'name' => $user->deanery->name,
            ] : null,
            'church' => $user->church ? [
                'id' => $user->church->id,
                'name' => $user->church->name,
            ] : null,
            'chapel' => $user->chapel ? [
                'id' => $user->chapel->id,
                'name' => $user->chapel->name,
            ] : null,
        ];
    }

    private function pendingModerationNotification(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $notification = InternalNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'moderation_warning')
            ->whereNull('read_at')
            ->latest('id')
            ->first();

        if (! $notification) {
            return null;
        }

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'level' => $notification->data['level'] ?? 'leve',
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function buildScopePayload(?User $user, ?array $resolvedPermissions = null): array
    {
        if (! $user) {
            return [
                'diocese_id' => null,
                'deanery_id' => null,
                'church_id' => null,
                'chapel_id' => null,
                'full_access' => [],
            ];
        }

        $permissionNames = collect($resolvedPermissions ?? $user->getAllPermissions()->pluck('name')->all());

        return [
            'diocese_id' => $user->diocese_id,
            'deanery_id' => $user->deanery_id,
            'church_id' => $user->church_id,
            'chapel_id' => $user->chapel_id,
            'can_see_externos' => (new UserScopeService($user))->canAccessExternosModule(),
            'full_access' => $permissionNames
                ->filter(fn (string $permission): bool => str_ends_with($permission, '.scope.all'))
                ->mapWithKeys(fn (string $permission): array => [
                    str($permission)->before('.scope.all')->toString() => true,
                ])
                ->all(),
        ];
    }

    private function resolveInitials(User $user): string
    {
        $firstName = trim((string) $user->profile?->name);
        $firstLastname = trim((string) $user->profile?->paterno);

        if ($firstName !== '' && $firstLastname !== '') {
            return mb_strtoupper(mb_substr($firstName, 0, 1).mb_substr($firstLastname, 0, 1));
        }

        $chunks = preg_split('/\s+/', trim($user->name)) ?: [];

        if (count($chunks) >= 2) {
            return mb_strtoupper(mb_substr($chunks[0], 0, 1).mb_substr($chunks[1], 0, 1));
        }

        return mb_strtoupper(mb_substr((string) ($chunks[0] ?? 'U'), 0, 1));
    }
}
