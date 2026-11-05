<?php

namespace App\Services\Masses;

use App\Models\Masses\Event;
use App\Models\Masses\EventAttendance;
use App\Models\User;
use App\Repositories\Masses\EventAttendanceRepository;

class EventAttendanceDataService
{
    public function __construct(private readonly EventAttendanceRepository $repository) {}

    public function getIndexData(User $user, Event $event, bool $canRead, bool $canScan): array
    {
        $event->loadMissing(['church:id,name']);

        $attendances = $this->repository->getAttendances($event, $canRead)
            ->through(fn ($attendance) => $this->repository->serializeAttendance($attendance));

        return [
            'event' => $this->repository->serializeEvent($event),
            'attendances' => $attendances,
            'canScan' => $canScan,
            'canManage' => $user->can('manage', [EventAttendance::class, $event]),
        ];
    }

    public function serializeEvent(Event $event): array
    {
        $event->loadMissing(['church:id,name']);

        return $this->repository->serializeEvent($event);
    }
}
