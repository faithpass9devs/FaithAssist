<?php

namespace App\Repositories\Regions;

use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\Municipality;
use App\Models\Regions\State;
use App\Models\User;
use App\Services\UserScopeService;

class MunicipalityRepository
{
    public function paginateWithSearch(User $user, string $search, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'state_id', 'diocese_id', 'name', 'status'])
            ->withQueryString();
    }

    public function activeStates(User $user)
    {
        $scope = new UserScopeService($user);

        return State::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->stateIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);
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

    public function create(array $data): Municipality
    {
        return Municipality::create($data);
    }

    public function update(Municipality $municipality, array $data): Municipality
    {
        $municipality->update($data);
        return $municipality->fresh();
    }

    public function delete(Municipality $municipality): void
    {
        $municipality->delete();
    }
}
