<?php

namespace App\Services\Masses;

use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\User;
use App\Repositories\Masses\MassAttendanceRepository;

class MassAttendanceDataService
{
    public function __construct(private readonly MassAttendanceRepository $repository) {}

    public function getIndexData(User $user, Mass $mass, bool $canRead, bool $canScan): array
    {
        $mass->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        $attendances = $this->repository->getAttendances($mass, $canRead)
            ->through(fn ($attendance) => $this->repository->serializeAttendance($attendance));

        return [
            'mass' => $this->repository->serializeMass($mass),
            'attendances' => $attendances,
            'canScan' => $canScan,
            'canManage' => $user->can('manage', [MassAttendance::class, $mass]),
        ];
    }

    public function serializeMass(Mass $mass): array
    {
        $mass->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        return $this->repository->serializeMass($mass);
    }
}
