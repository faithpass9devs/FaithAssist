<?php

namespace App\Services\Operation;

use App\Models\Operation\StaffType;
use App\Repositories\Operation\StaffTypeRepository;

class StaffTypeService
{
    public function __construct(private readonly StaffTypeRepository $staffTypes) {}

    public function indexData(string $search): array
    {
        return [
            'staffTypes' => $this->staffTypes->paginateWithSearch($search),
            'search' => $search,
        ];
    }

    public function createStaffType(array $data): array
    {
        return $this->staffTypeData($this->staffTypes->create($data));
    }

    public function updateStaffType(StaffType $staffType, array $data): array
    {
        return $this->staffTypeData($this->staffTypes->update($staffType, $data));
    }

    public function deleteStaffType(StaffType $staffType): void
    {
        $this->staffTypes->delete($staffType);
    }

    private function staffTypeData(StaffType $staffType): array
    {
        return $staffType->only(['id', 'name', 'description', 'status']);
    }
}
