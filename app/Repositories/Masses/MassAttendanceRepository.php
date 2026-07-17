<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\User;
use App\Services\UserScopeService;

class MassAttendanceRepository
{
    public function getAvailableMasses(User $user)
    {
        $scope = new UserScopeService($user);
        $now = now();
        $nowTimestamp = $now->getTimestamp();

        return $scope->applyMassScope(
            Mass::query()->with(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name'])
        )
            ->get()
            ->sortBy(function (Mass $mass) use ($nowTimestamp): string {
                $startTimestamp = $mass->starts_at?->getTimestamp()
                    ?? $mass->weekend?->starts_at?->getTimestamp()
                    ?? PHP_INT_MAX;
                $endTimestamp = $mass->ends_at?->getTimestamp()
                    ?? $mass->weekend?->ends_at?->getTimestamp()
                    ?? $startTimestamp;

                if ($mass->attendance_status === 'in_progress' || ($startTimestamp <= $nowTimestamp && $endTimestamp >= $nowTimestamp)) {
                    $priority = 0;
                    $distance = 0;
                } elseif ($startTimestamp >= $nowTimestamp) {
                    $priority = 1;
                    $distance = $startTimestamp - $nowTimestamp;
                } else {
                    $priority = 2;
                    $distance = $nowTimestamp - $endTimestamp;
                }

                return sprintf('%d-%020d-%020d', $priority, $distance, $startTimestamp);
            })
            ->values();
    }

    public function getAttendances(Mass $mass, bool $canRead, int $page = 1)
    {
        return MassAttendance::query()
            ->with(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name', 'mass:id,weekend_id'])
            ->where('mass_id', $mass->id)
            ->when(! $canRead, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function serializeMass(Mass $mass): array
    {
        return [
            'id' => $mass->id,
            'weekend_id' => $mass->weekend_id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format('Y-m-d h:i A'),
            'ends_at' => $mass->ends_at?->format('Y-m-d h:i A'),
            'attendance_status' => $mass->attendance_status,
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

        $incident = $attendance->activeIncident();

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

    public function getWeekendOptions(User $user): array
    {
        return $this->getAvailableMasses($user)
            ->groupBy('weekend_id')
            ->map(function ($masses) {
                $firstMass = $masses->first();
                $weekend = $firstMass?->weekend;

                return [
                    'id' => $firstMass?->weekend_id,
                    'label' => $weekend?->name ?: $weekend?->starts_at?->format('Y-m-d') ?: 'Sin fin de semana',
                    'starts_at' => $weekend?->starts_at?->format('Y-m-d h:i A'),
                    'masses' => $masses->map(function (Mass $mass): array {
                        $location = $mass->chapel?->name ?: $mass->church?->name;
                        $schedule = collect([
                            $mass->starts_at?->format('Y-m-d h:i A'),
                            $mass->ends_at?->format('Y-m-d h:i A'),
                        ])->filter()->implode(' - ');

                        return [
                            'id' => $mass->id,
                            'short_label' => $schedule,
                            'label' => collect([
                                $schedule,
                                $location,
                            ])->filter()->implode(' · '),
                            'location' => $location,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
