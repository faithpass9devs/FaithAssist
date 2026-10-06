<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;

class MassAttendanceRepository
{
    public function getAttendances(Mass $mass, bool $canRead, int $page = 1)
    {
        $attendances = MassAttendance::query()
            ->with(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name', 'mass:id,weekend_id'])
            ->where('mass_id', $mass->id)
            ->when(! $canRead, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $this->preloadActiveIncidents($attendances, $mass);

        return $attendances;
    }

    private function preloadActiveIncidents($attendances, Mass $mass): void
    {
        if ($attendances->isEmpty() || ! $mass->weekend_id) {
            return;
        }

        $childIds = collect($attendances->items())
            ->pluck('child_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($childIds)) {
            return;
        }

        $incidents = MassAttendanceIncident::query()
            ->with('incidenceType:id,name')
            ->where('weekend_id', $mass->weekend_id)
            ->whereIn('child_id', $childIds)
            ->where('status', Status::ACTIVE)
            ->get()
            ->keyBy('child_id');

        collect($attendances->items())->each(function (MassAttendance $attendance) use ($incidents): void {
            $attendance->setRelation('activeIncident', $incidents->get($attendance->child_id));
        });
    }

    public function serializeMass(Mass $mass): array
    {
        return [
            'id' => $mass->id,
            'weekend_id' => $mass->weekend_id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format('Y-m-d h:i A'),
            'ends_at' => $mass->ends_at?->format('Y-m-d h:i A'),
            'attendance_check_in_status' => $mass->attendance_check_in_status,
            'attendance_check_out_status' => $mass->attendance_check_out_status,
            'church' => $mass->church?->name,
            'chapel' => $mass->chapel?->name,
            'location' => $mass->chapel?->name ?: $mass->church?->name,
            'weekend' => $mass->weekend?->name ?: $mass->weekend?->starts_at?->format('Y-m-d'),
        ];
    }

    public function serializeAttendance($attendance): array
    {
        $childName = trim(collect([
            $attendance->child?->name,
            $attendance->child?->paterno,
            $attendance->child?->materno,
        ])->filter()->implode(' '));

        $incident = $attendance->relationLoaded('activeIncident')
            ? $attendance->getRelation('activeIncident')
            : $attendance->activeIncident();

        return [
            'id' => $attendance->id,
            'child_id' => $attendance->child_id,
            'child_code' => $attendance->child_code,
            'child_name' => $childName,
            'church' => $attendance->church?->name,
            'chapel' => $attendance->chapel?->name,
            'location' => $attendance->chapel?->name ?: $attendance->church?->name,
            'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
            'status' => $attendance->status,
            'valid' => $attendance->isValidAttendance(),
            'justified' => $incident !== null,
            'incidence_type' => $incident?->incidenceType?->name,
            'incidence_description' => $incident?->description,
        ];
    }
}
