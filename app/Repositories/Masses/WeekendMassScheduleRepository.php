<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\Masses\WeekendMassSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WeekendMassScheduleRepository
{
    public function forChurch(int $churchId): Collection
    {
        return WeekendMassSchedule::query()
            ->with('chapel:id,name')
            ->where('church_id', $churchId)
            ->orderByRaw("CASE WHEN day_of_week = 'saturday' THEN 0 ELSE 1 END")
            ->orderBy('starts_time')
            ->orderBy('id')
            ->get();
    }

    public function churchIdsWithSchedules(Collection $churchIds): array
    {
        return WeekendMassSchedule::query()
            ->whereIn('church_id', $churchIds)
            ->distinct()
            ->pluck('church_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function activeChapelsForChurch(int $churchId): Collection
    {
        return Chapel::query()
            ->where('church_id', $churchId)
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'church_id', 'name']);
    }

    public function create(array $data): WeekendMassSchedule
    {
        return WeekendMassSchedule::create($data);
    }

    public function update(WeekendMassSchedule $schedule, array $data): WeekendMassSchedule
    {
        $schedule->update($data);

        return $schedule;
    }

    public function delete(WeekendMassSchedule $schedule, ?int $userId): void
    {
        $schedule->forceFill(['deleted_by' => $userId])->save();
        $schedule->delete();
    }

    public function weekendOverlaps(int $churchId, Carbon $startsAt, Carbon $endsAt): bool
    {
        return Weekend::query()
            ->where('church_id', $churchId)
            ->where('starts_at', '<=', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>=', $startsAt->format('Y-m-d H:i:s'))
            ->exists();
    }

    public function createWeekend(array $data): Weekend
    {
        return Weekend::create($data);
    }

    public function createMass(array $data): Mass
    {
        return Mass::create($data);
    }

    public function serializeSchedule(WeekendMassSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'name' => $schedule->name,
            'starts_time' => substr((string) $schedule->starts_time, 0, 5),
            'ends_time' => substr((string) $schedule->ends_time, 0, 5),
            'chapel_id' => $schedule->chapel_id,
            'chapel' => $schedule->chapel?->name,
        ];
    }
}
