<?php

namespace App\Services\Operation;

use App\Globals\Status;
use App\Models\Operation\PeriodMovementType;
use App\Repositories\Operation\PeriodMovementTypeRepository;

class PeriodMovementTypeService
{
    public function __construct(private readonly PeriodMovementTypeRepository $types) {}

    public function indexData(string $search): array
    {
        return [
            'movementTypes' => $this->types->paginateWithSearch($search),
            'search' => $search,
            'statusOptions' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    public function createMovementType(array $data): array
    {
        $type = $this->types->create($data);
        return $this->movementTypeData($type);
    }

    public function updateMovementType(PeriodMovementType $type, array $data): array
    {
        $type = $this->types->update($type, $data);
        return $this->movementTypeData($type);
    }

    public function deleteMovementType(PeriodMovementType $type): void
    {
        $this->types->delete($type);
    }

    private function movementTypeData(PeriodMovementType $type): array
    {
        return $type->only(['id', 'name', 'description', 'status']);
    }
}
