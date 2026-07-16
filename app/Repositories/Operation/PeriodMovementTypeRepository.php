<?php

namespace App\Repositories\Operation;

use App\Models\Operation\PeriodMovementType;

class PeriodMovementTypeRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return PeriodMovementType::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'status'])
            ->withQueryString();
    }

    public function create(array $data): PeriodMovementType
    {
        return PeriodMovementType::create($data);
    }

    public function update(PeriodMovementType $type, array $data): PeriodMovementType
    {
        $type->update($data);
        return $type->fresh();
    }

    public function delete(PeriodMovementType $type): void
    {
        $type->delete();
    }
}
