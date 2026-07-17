<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\User;
use App\Services\UserScopeService;

class MassRepository
{
    public function paginateWithScope(User $user, string $search, ?int $weekendId = null, int $perPage = 72)
    {
        $scope = new UserScopeService($user);

        return $scope->applyMassScope(
            Mass::query()
                ->with(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name'])
                ->when($weekendId, fn ($query) => $query->where('weekend_id', $weekendId))
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($builder) use ($search) {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('chapel', fn ($chapel) => $chapel->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('starts_at')
        )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeWeekends(User $user): array
    {
        $scope = new UserScopeService($user);

        return $scope->applyWeekendScope(
            Weekend::query()
                ->with('church:id,name')
                ->orderByDesc('starts_at')
        )
            ->get(['id', 'church_id', 'name', 'starts_at', 'ends_at', 'status'])
            ->map(fn (Weekend $weekend): array => [
                'id' => $weekend->id,
                'church_id' => $weekend->church_id,
                'name' => $weekend->name ?: $weekend->starts_at?->format('Y-m-d'),
                'starts_at' => $weekend->starts_at?->format('Y-m-d h:i A'),
                'ends_at' => $weekend->ends_at?->format('Y-m-d h:i A'),
                'status' => $weekend->status,
                'church' => $weekend->church?->name,
            ])
            ->all();
    }

    public function activeChurches(User $user): array
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    public function activeChapels(User $user): array
    {
        $scope = new UserScopeService($user);

        if (! $scope->isGlobal() && ! $user->can('masses.scope.all') && $user->chapel_id === null) {
            return [];
        }

        return Chapel::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->chapelIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'church_id', 'name'])
            ->all();
    }

    public function create(array $data): Mass
    {
        return Mass::create($data);
    }

    public function update(Mass $mass, array $data): Mass
    {
        $mass->update($data);
        return $mass->fresh();
    }

    public function delete(Mass $mass): void
    {
        $mass->delete();
    }

    public function serializeMass(Mass $mass, bool $forForm = false): array
    {
        return [
            'id' => $mass->id,
            'weekend_id' => $mass->weekend_id,
            'church_id' => $mass->church_id,
            'chapel_id' => $mass->chapel_id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format($forForm ? 'Y-m-d\TH:i' : 'Y-m-d h:i A'),
            'ends_at' => $mass->ends_at?->format($forForm ? 'Y-m-d\TH:i' : 'Y-m-d h:i A'),
            'status' => $mass->status,
            'attendance_status' => $mass->attendance_status,
            'notes' => $mass->notes,
            'weekend' => $mass->weekend?->name ?: $mass->weekend?->starts_at?->format('Y-m-d'),
            'church' => $mass->church?->name,
            'chapel' => $mass->chapel?->name,
            'location' => $mass->chapel?->name ?: $mass->church?->name,
        ];
    }
}
