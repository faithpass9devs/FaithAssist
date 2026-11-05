<?php

namespace App\Policies;

use App\Models\Masses\Event;
use App\Models\Masses\EventAttendance;
use App\Models\User;
use App\Services\UserScopeService;

class EventAttendancePolicy extends BasePermissionPolicy
{
    protected function permissionModule(): string
    {
        return 'event_attendance';
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'read');
    }

    public function view(User $user, EventAttendance $eventAttendance): bool
    {
        return $this->can($user, 'show') && $this->withinScope($user, $eventAttendance);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function read(User $user, Event $event): bool
    {
        return $this->can($user, 'read') && $this->eventWithinScope($user, $event);
    }

    public function scan(User $user, Event $event): bool
    {
        return $this->can($user, 'scan') && $this->eventWithinScope($user, $event);
    }

    public function manage(User $user, Event $event): bool
    {
        return $this->can($user, 'manage') && $this->eventWithinScope($user, $event);
    }

    public function update(User $user, EventAttendance $eventAttendance): bool
    {
        return $this->can($user, 'update') && $this->withinScope($user, $eventAttendance);
    }

    public function delete(User $user, EventAttendance $eventAttendance): bool
    {
        return $this->can($user, 'delete') && $this->withinScope($user, $eventAttendance);
    }

    private function withinScope(User $user, EventAttendance $eventAttendance): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->churchIds()->contains($eventAttendance->church_id);
    }

    private function eventWithinScope(User $user, Event $event): bool
    {
        if ($this->hasFullScope($user)) {
            return true;
        }

        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $scope->churchIds()->contains($event->church_id);
    }
}
