<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Weekend;
use App\Models\User;
use App\Repositories\Masses\WeekendRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeekendService
{
    public function __construct(
        private readonly WeekendRepository $weekends,
        private readonly WeekendScheduleService $schedules,
    ) {}

    public const TABS = ['current', 'upcoming', 'history'];

    /**
     * Pestañas del índice que el usuario puede ver, en el orden de la UI.
     *
     * @return list<string>
     */
    public function allowedTabs(User $user): array
    {
        return array_values(array_filter(
            self::TABS,
            fn (string $tab): bool => $user->can("weekends.tab.{$tab}"),
        ));
    }

    public function indexData(User $user, string $search, ?string $tab = 'current'): array
    {
        $query = $this->weekends->queryWithScope($user, $search);
        $weekends = match ($tab) {
            'history' => (clone $query)->completed()->reorder('starts_at', 'desc'),
            'upcoming' => (clone $query)->upcoming()->reorder('starts_at'),
            'current' => (clone $query)->progress(),
            default => (clone $query)->whereRaw('1 = 0'),
        };

        return [
            'weekends' => $this->weekends->paginateQuery($weekends, 5, 'weekends_page')
                ->through(fn (Weekend $weekend) => $this->weekends->serializeWeekend($weekend)),
            'churches' => $this->schedules->churchOptions($user),
            'search' => $search,
            'tab' => $tab,
            ...$this->schedules->indexData($user),
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'weekend' => null,
            'churches' => $this->schedules->churchOptions($user),
            'lockedChurchId' => $this->schedules->lockedChurchId($user),
        ];
    }

    public function editFormData(User $user, Weekend $weekend): array
    {
        $weekend->load('church:id,name');

        return [
            'weekend' => $this->weekends->serializeWeekend($weekend, true),
            'churches' => $this->schedules->churchOptions($user),
            'lockedChurchId' => $this->schedules->lockedChurchId($user),
        ];
    }

    public function createWeekend(array $data): Weekend
    {
        if (($data['status'] ?? null) !== Status::UPCOMING) {
            throw ValidationException::withMessages([
                'status' => 'Los fines de semana nuevos deben crearse como próximos.',
            ]);
        }

        return DB::transaction(function () use ($data): Weekend {
            Church::query()->whereKey($data['church_id'])->lockForUpdate()->firstOrFail();
            $this->ensureStatusAvailable((int) $data['church_id'], $data['status']);

            return $this->weekends->create($data);
        });
    }

    public function updateWeekend(Weekend $weekend, array $data): Weekend
    {
        return DB::transaction(function () use ($weekend, $data): Weekend {
            $churchIds = collect([$weekend->church_id, $data['church_id']])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();

            Church::query()
                ->whereIn('id', $churchIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedWeekend = Weekend::query()->lockForUpdate()->findOrFail($weekend->id);
            $previousStatus = $lockedWeekend->status;
            $updatedWeekend = $this->weekends->update($lockedWeekend, $data);
            $this->ensureStatusAvailable(
                (int) $updatedWeekend->church_id,
                $updatedWeekend->status,
                $updatedWeekend->id,
            );

            if ($previousStatus !== Status::COMPLETED && $updatedWeekend->status === Status::COMPLETED) {
                $this->startNextWeekend($updatedWeekend);
            }

            return $updatedWeekend;
        });
    }

    private function startNextWeekend(Weekend $completedWeekend): void
    {
        $inProgressExists = Weekend::query()
            ->where('church_id', $completedWeekend->church_id)
            ->where('status', Status::IN_PROGRESS)
            ->exists();

        if ($inProgressExists) {
            return;
        }

        $nextWeekend = Weekend::query()
            ->where('church_id', $completedWeekend->church_id)
            ->whereKeyNot($completedWeekend->id)
            ->where('starts_at', '>', $completedWeekend->starts_at)
            ->where('status', Status::UPCOMING)
            ->orderBy('starts_at')
            ->lockForUpdate()
            ->first();

        if ($nextWeekend) {
            $this->weekends->update($nextWeekend, ['status' => Status::IN_PROGRESS]);
        }
    }

    public function deleteWeekend(Weekend $weekend, ?int $userId = null): void
    {
        DB::transaction(fn () => $this->weekends->delete($weekend, $userId));
    }

    private function ensureStatusAvailable(int $churchId, string $status, ?int $ignoreWeekendId = null): void
    {
        if ($status !== Status::IN_PROGRESS) {
            return;
        }

        $exists = Weekend::query()
            ->where('church_id', $churchId)
            ->where('status', $status)
            ->when($ignoreWeekendId, fn ($query) => $query->whereKeyNot($ignoreWeekendId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => 'Solo puede existir un fin de semana en curso.',
            ]);
        }
    }
}
