<?php

namespace App\Services\Masses;

use App\Models\User;
use App\Repositories\Masses\ManualAttendanceRepository;

class ManualAttendanceDataService
{
    public function __construct(private readonly ManualAttendanceRepository $manualAttendance) {}

    public function indexData(
        User $user,
        string $code,
        string $name,
        string $levelId,
        ?int $municipalityId,
        ?int $communityId,
        ?int $childId,
        ?int $weekendId
    ): array {
        $children = $this->manualAttendance->getChildren(
            $user,
            $code,
            $name,
            $levelId,
            $municipalityId,
            $communityId
        );

        $weekends = $this->manualAttendance->getWeekends($user);
        $filterOptions = $this->manualAttendance->getFilterOptions($user);

        $masses = $weekendId
            ? $this->manualAttendance->getMasses($user, $weekendId)
            : [];

        return [
            'children' => $children,
            'weekends' => $weekends,
            'masses' => $masses,
            'levels' => $filterOptions['levels'],
            'municipalities' => $filterOptions['municipalities'],
            'communities' => $filterOptions['communities'],
            'filters' => [
                'code' => $code,
                'name' => $name,
                'level_id' => $levelId !== '' ? $levelId : null,
                'municipality_id' => $municipalityId,
                'community_id' => $communityId,
                'child_id' => $childId,
                'weekend_id' => $weekendId,
            ],
        ];
    }
}
