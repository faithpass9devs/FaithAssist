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
    public function __construct(private readonly WeekendRepository $weekends) {}

    public function indexData(User $user, string $search, string $tab = 'current'): array
    {
        $query = $this->weekends->queryWithScope($user, $search);
        $weekends = match ($tab) {
            'history' => (clone $query)->completed()->reorder('starts_at', 'desc'),
            'upcoming' => (clone $query)->upcoming()->reorder('starts_at'),
            default => (clone $query)->progress(),
        };

        return [
            'weekends' => $this->weekends->paginateQuery($weekends, 5, 'weekends_page')
                ->through(fn (Weekend $weekend) => $this->weekends->serializeWeekend($weekend)),
            'churches' => $this->weekends->activeChurches($user),
            'search' => $search,
            'tab' => $tab,
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'weekend' => null,
            'churches' => $this->weekends->activeChurches($user),
        ];
    }

    public function editFormData(User $user, Weekend $weekend): array
    {
        $weekend->load('church:id,name');

        return [
            'weekend' => $this->weekends->serializeWeekend($weekend, true),
            'churches' => $this->weekends->activeChurches($user),
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

            if ($previousStatus === Status::IN_PROGRESS && $updatedWeekend->status === Status::COMPLETED) {
                $nextWeekend = Weekend::query()
                    ->where('church_id', $updatedWeekend->church_id)
                    ->where('id', '!=', $updatedWeekend->id)
                    ->where('starts_at', '>', $updatedWeekend->starts_at)
                    ->where('status', '!=', Status::COMPLETED)
                    ->orderBy('starts_at')
                    ->lockForUpdate()
                    ->first();

                if ($nextWeekend && $nextWeekend->status !== Status::UPCOMING) {
                    $upcomingWeekendExists = Weekend::query()
                        ->where('church_id', $nextWeekend->church_id)
                        ->where('status', Status::UPCOMING)
                        ->exists();

                    if (! $upcomingWeekendExists) {
                        $nextWeekend = $this->weekends->update($nextWeekend, ['status' => Status::UPCOMING]);
                    }
                }
            }

            return $updatedWeekend;
        });
    }

    public function deleteWeekend(Weekend $weekend): void
    {
        $this->weekends->delete($weekend);
    }

    private function ensureStatusAvailable(int $churchId, string $status, ?int $ignoreWeekendId = null): void
    {
        if (! in_array($status, [Status::UPCOMING, Status::IN_PROGRESS], true)) {
            return;
        }

        $exists = Weekend::query()
            ->where('church_id', $churchId)
            ->where('status', $status)
            ->when($ignoreWeekendId, fn ($query) => $query->whereKeyNot($ignoreWeekendId))
            ->exists();

        if ($exists) {
            $message = $status === Status::UPCOMING
                ? 'Ya existe un fin de semana próximo para esta parroquia.'
                : 'Solo puede existir un fin de semana en curso por parroquia.';

            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
