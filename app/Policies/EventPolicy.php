<?php

namespace App\Policies;

use App\Models\Masses\Event;
use App\Models\User;
use App\Services\UserScopeService;

class EventPolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'eventos';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->can($user, 'show') && $this->withinVisibleScope($user, $event);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create') && $this->canManageChurchEvents($user, null);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->can($user, 'update') && $this->canManageChurchEvents($user, $event->church_id);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->can($user, 'delete') && $this->canManageChurchEvents($user, $event->church_id);
    }

    private function withinVisibleScope(User $user, Event $event): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->churchIds()->contains($event->church_id);
    }

    private function canManageChurchEvents(User $user, ?int $churchId): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($churchId === null) {
            return $user->church_id !== null;
        }

        return $user->church_id !== null
            && (int) $user->church_id === (int) $churchId
            && $user->chapel_id === null;
    }
}
