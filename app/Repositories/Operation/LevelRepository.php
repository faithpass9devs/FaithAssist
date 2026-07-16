<?php

namespace App\Repositories\Operation;

use App\Models\Ecclesiastes\Diocese;
use App\Models\Operation\Level;
use App\Models\User;
use App\Services\UserScopeService;

class LevelRepository
{
    public function paginateWithSearch(User $user, string $search, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return Level::query()
            ->with('diocese:id,name')
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhereHas('diocese', fn ($dioceseQuery) => $dioceseQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'diocese_id', 'name', 'description', 'status'])
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

    public function create(array $data): Level
    {
        return Level::create($data);
    }

    public function update(Level $level, array $data): Level
    {
        $level->update($data);
        return $level->fresh();
    }

    public function delete(Level $level): void
    {
        $level->delete();
    }
}
