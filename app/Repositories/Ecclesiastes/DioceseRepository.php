<?php

namespace App\Repositories\Ecclesiastes;

use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\State;
use App\Models\User;

class DioceseRepository
{
    public function paginate(User $user, string $search, int $perPage = 15)
    {
        $scope = new \App\Services\UserScopeService($user);

        return Diocese::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn(
                'id',
                $scope->dioceseIds()
            ))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'state_id', 'name', 'bishop', 'status'])
            ->withQueryString();
    }

    public function activeStates(User $user)
    {
        $scope = new \App\Services\UserScopeService($user);

        return State::query()
            ->where('status', 'active')
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn(
                'id',
                $scope->stateIds()
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);
    }

    public function create(array $data): Diocese
    {
        return Diocese::create($data);
    }

    public function update(Diocese $diocese, array $data): Diocese
    {
        $diocese->update($data);
        return $diocese->fresh();
    }

    public function delete(Diocese $diocese): void
    {
        $diocese->delete();
    }
}
