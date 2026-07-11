<?php

namespace App\Repositories\Regions;

use App\Models\Regions\State;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StateRepository
{
    public function paginateVisibleStates(User $user, string $search, int $perPage = 15): LengthAwarePaginator
    {
        $scope = new UserScopeService($user);

        return State::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->stateIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'short_name', 'status'])
            ->withQueryString();
    }

    public function create(array $data): State
    {
        return State::create($data);
    }

    public function update(State $state, array $data): State
    {
        $state->update($data);

        return $state->fresh();
    }

    public function delete(State $state): void
    {
        $state->delete();
    }
}
