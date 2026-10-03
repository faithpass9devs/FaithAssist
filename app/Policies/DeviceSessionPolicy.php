<?php

namespace App\Policies;

use App\Models\DeviceSession;
use App\Models\User;
use App\Services\Security\DeviceSessionVisibilityService;

class DeviceSessionPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'dispositivos_sesiones';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, DeviceSession $deviceSession): bool
    {
        return ($this->can($user, 'show') || $this->can($user, 'read'))
            && $this->withinScope($user, $deviceSession);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DeviceSession $deviceSession): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $deviceSession);
    }

    public function delete(User $user, DeviceSession $deviceSession): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $deviceSession);
    }

    private function withinScope(User $user, DeviceSession $deviceSession): bool
    {
        $target = $deviceSession->user;

        return $target !== null && app(DeviceSessionVisibilityService::class)->canView($user, $target);
    }
}
