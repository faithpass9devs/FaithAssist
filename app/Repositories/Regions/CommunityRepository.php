<?php

namespace App\Repositories\Regions;

use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;

class CommunityRepository
{
    public function paginateWithSearch(User $user, string $search, ?int $municipalityId = null, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return Community::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('municipality_id', $scope->municipalityIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'municipality_id', 'name', 'status'])
            ->withQueryString();
    }

    public function activeMunicipalities(User $user)
    {
        $scope = new UserScopeService($user);

        return Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Community
    {
        return Community::create($data);
    }

    public function update(Community $community, array $data): Community
    {
        $community->update($data);
        return $community->fresh();
    }

    public function delete(Community $community): void
    {
        $community->delete();
    }
}
