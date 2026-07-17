<?php

namespace App\Repositories\Ecclesiastes;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;

class ChurchRepository
{
    public function paginateWithFilters(User $user, string $search, ?int $municipalityId = null, ?int $deaneryId = null, ?string $status = null, int $perPage = 15)
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->with(['municipality:id,name', 'deanery:id,name'])
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            ->when($deaneryId, fn ($q) => $q->where('deanery_id', $deaneryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeMunicipalities(User $user)
    {
        $scope = new UserScopeService($user);

        return Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function activeDeaneries(User $user)
    {
        $scope = new UserScopeService($user);

        return Deanery::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->deaneryIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Church
    {
        return Church::create($data);
    }

    public function update(Church $church, array $data): Church
    {
        $church->update($data);
        return $church->fresh();
    }

    public function delete(Church $church): void
    {
        $church->delete();
    }

    public function serializeChurch(Church $church): array
    {
        return [
            'id' => $church->id,
            'municipality_id' => $church->municipality_id,
            'deanery_id' => $church->deanery_id,
            'name' => $church->name,
            'alias' => $church->alias,
            'email' => $church->email,
            'phone' => $church->phone,
            'address' => $church->address,
            'status' => $church->status,
            'municipality' => $church->municipality?->name,
            'deanery' => $church->deanery?->name,
        ];
    }
}
