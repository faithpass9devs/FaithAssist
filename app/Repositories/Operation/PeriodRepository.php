<?php

namespace App\Repositories\Operation;

use App\Models\Ecclesiastes\Diocese;
use App\Models\Operation\Period;
use App\Models\User;
use App\Services\UserScopeService;

class PeriodRepository
{
    public function paginateWithSearch(User $user, string $search, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return Period::query()
            ->with('diocese:id,name')
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('years', 'like', "%{$search}%")
                        ->orWhereHas('diocese', fn ($dioceseQuery) => $dioceseQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('start_date')
            ->paginate($perPage, ['id', 'diocese_id', 'name', 'start_date', 'end_date', 'years', 'status'])
            ->withQueryString();
    }

    public function activeDioceses(User $user)
    {
        $scope = new UserScopeService($user);

        return Diocese::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->dioceseIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Period
    {
        return Period::create($data);
    }

    public function update(Period $period, array $data): Period
    {
        $period->update($data);
        return $period->fresh();
    }

    public function delete(Period $period): void
    {
        $period->delete();
    }
}
