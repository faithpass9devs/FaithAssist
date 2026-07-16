<?php

namespace App\Repositories\Ecclesiastes;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Regions\Community;
use App\Models\User;
use App\Services\UserScopeService;

class ChapelRepository
{
    public function paginateWithScope(User $user, string $search, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return $scope->applyChapelScope(
            Chapel::query()
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
        )
            ->paginate($perPage, ['id', 'community_id', 'church_id', 'name', 'address', 'status'])
            ->withQueryString();
    }

    public function activeCommunities(User $user)
    {
        $scope = new UserScopeService($user);

        return Community::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->communityIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function activeChurches(User $user)
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Chapel
    {
        return Chapel::create($data);
    }

    public function update(Chapel $chapel, array $data): Chapel
    {
        $chapel->update($data);
        return $chapel->fresh();
    }

    public function delete(Chapel $chapel): void
    {
        $chapel->delete();
    }
}
