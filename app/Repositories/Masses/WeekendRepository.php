<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Weekend;
use App\Models\User;
use App\Services\UserScopeService;

class WeekendRepository
{
    public function paginateWithScope(User $user, string $search, int $perPage = 5)
    {
        $scope = new UserScopeService($user);

        return $scope->applyWeekendScope(
            Weekend::query()
                ->with('church:id,name')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($builder) use ($search) {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('starts_at')
        )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeChurches(User $user)
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Weekend
    {
        return Weekend::create($data);
    }

    public function update(Weekend $weekend, array $data): Weekend
    {
        $weekend->update($data);
        return $weekend->fresh();
    }

    public function delete(Weekend $weekend): void
    {
        $weekend->delete();
    }

    public function serializeWeekend(Weekend $weekend, bool $forForm = false): array
    {
        return [
            'id' => $weekend->id,
            'church_id' => $weekend->church_id,
            'church' => $weekend->church?->name,
            'name' => $weekend->name,
            'starts_at' => $weekend->starts_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'ends_at' => $weekend->ends_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'status' => $weekend->status,
        ];
    }
}
