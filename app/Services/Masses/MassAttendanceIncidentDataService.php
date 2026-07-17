<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\User;
use App\Repositories\Masses\MassAttendanceIncidentRepository;

class MassAttendanceIncidentDataService
{
    public function __construct(private readonly MassAttendanceIncidentRepository $incidents) {}

    public function indexData(
        User $user,
        string $search,
        ?int $weekendId = null,
        ?int $childId = null,
        ?string $status = null
    ): array {
        $incidents = $this->incidents->paginateWithFilters(
            $user,
            $search,
            $weekendId,
            $childId,
            $status
        );

        return [
            'incidents' => $incidents->through(fn (MassAttendanceIncident $incident) => $this->incidents->serializeIncident($incident)),
            'weekends' => $this->incidents->getWeekendOptions($user),
            'children' => $this->incidents->getChildOptions($user),
            'incidenceTypes' => $this->incidents->getIncidenceTypeOptions(),
            'statusOptions' => $this->statusOptions(),
            'search' => $search,
            'filters' => [
                'weekend_id' => $weekendId,
                'child_id' => $childId,
                'status' => $status,
            ],
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => Status::ACTIVE, 'label' => 'Activo'],
            ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
        ];
    }
}
