<?php

namespace App\Repositories\Operation;

use App\Models\Operation\StaffType;

class StaffTypeRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return StaffType::query()
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'status'])
            ->withQueryString();
    }

    public function create(array $data): StaffType
    {
        return StaffType::create($data);
    }

    public function update(StaffType $staffType, array $data): StaffType
    {
        $staffType->update($data);

        return $staffType->fresh();
    }

    public function delete(StaffType $staffType): void
    {
        $staffType->delete();
    }

    public function activeOptions()
    {
        return StaffType::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
