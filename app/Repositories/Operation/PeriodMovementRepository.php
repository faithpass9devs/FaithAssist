<?php

namespace App\Repositories\Operation;

use App\Globals\Status;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\User;
use App\Services\UserScopeService;

class PeriodMovementRepository
{
    public function paginateWithSearch(User $user, string $search, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return PeriodMovement::query()
            ->with([
                'period:id,diocese_id,name,years',
                'period.diocese:id,name',
                'periodMovementType:id,name,status',
            ])
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereHas(
                'period',
                fn ($p) => $p->whereIn('diocese_id', $scope->dioceseIds())
            ))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('status', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('periodMovementType', fn ($movementTypeQuery) => $movementTypeQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('period', fn ($periodQuery) => $periodQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('years', 'like', "%{$search}%")
                            ->orWhereHas('diocese', fn ($dioceseQuery) => $dioceseQuery->where('name', 'like', "%{$search}%")));
                });
            })
            ->orderByDesc('start_date')
            ->paginate($perPage, ['id', 'period_id', 'period_movement_type_id', 'status', 'start_date', 'end_date', 'notes'])
            ->withQueryString();
    }

    public function activePeriods(User $user)
    {
        $scope = new UserScopeService($user);

        return Period::query()
            ->with('diocese:id,name')
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
            ->orderByDesc('start_date')
            ->get(['id', 'diocese_id', 'name', 'years']);
    }

    public function activeMovementTypes()
    {
        return PeriodMovementType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'description']);
    }

    public function create(array $data): PeriodMovement
    {
        return PeriodMovement::create($data);
    }

    public function update(PeriodMovement $movement, array $data): PeriodMovement
    {
        $movement->update($data);
        return $movement->fresh();
    }

    public function delete(PeriodMovement $movement): void
    {
        $movement->delete();
    }
}
