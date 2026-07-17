<?php

namespace App\Repositories\Masses;

use App\Models\Masses\IncidenceType;

class IncidenceTypeRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return IncidenceType::query()
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

    public function create(array $data): IncidenceType
    {
        return IncidenceType::create($data);
    }

    public function update(IncidenceType $incidenceType, array $data): IncidenceType
    {
        $incidenceType->update($data);
        return $incidenceType->fresh();
    }

    public function delete(IncidenceType $incidenceType): void
    {
        $incidenceType->delete();
    }
}
