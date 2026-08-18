<?php

namespace App\Repositories\Ecclesiastes;

use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\User;

class DeaneryRepository
{
    public function paginate(User $user, string $search, int $perPage = 15)
    {
        return Deanery::query()
            ->when(! (new \App\Services\UserScopeService($user))->isGlobal(), fn ($q) => $q->whereIn(
                'id',
                (new \App\Services\UserScopeService($user))->deaneryIds()
            ))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'diocese_id', 'name', 'status'])
            ->withQueryString();
    }

    public function activeDioceses(User $user)
    {
        return Diocese::query()
            ->where('status', 'active')
            ->when(! (new \App\Services\UserScopeService($user))->isGlobal(), fn ($q) => $q->whereIn(
                'id',
                (new \App\Services\UserScopeService($user))->dioceseIds()
            ))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Deanery
    {
        return Deanery::create($data);
    }

    public function update(Deanery $deanery, array $data): Deanery
    {
        $deanery->update($data);
        return $deanery->fresh();
    }

    public function delete(Deanery $deanery): void
    {
        $deanery->delete();
    }
}
