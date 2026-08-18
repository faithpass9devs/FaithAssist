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

        // Check if manual attendance capture is currently active
        $isManualAttendanceActive = $this->manualAttendance->isManualAttendanceCaptureActive($user);
        $activeMovement = $isManualAttendanceActive
            ? $this->manualAttendance->getActiveManualAttendanceMovementInfo($user)
            : null;

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
            // Movement validation info for frontend
            'movement' => [
                'is_active' => $isManualAttendanceActive,
                'active_movement' => $activeMovement,
                'message' => $isManualAttendanceActive
                    ? "Movimiento activo: {$activeMovement['type_name']} ({$activeMovement['period_name']})"
                    : 'No hay un movimiento activo de asistencia manual. Contacta al administrador.',
            ],
        ];
    }

    /**
     * Check if manual attendance capture is currently active.
     * This method validates if a manual attendance movement is active and available for use.
     */
    public function isManualAttendanceCaptureActive(User $user): bool
    {
        return $this->manualAttendance->isManualAttendanceCaptureActive($user);
    }
}
