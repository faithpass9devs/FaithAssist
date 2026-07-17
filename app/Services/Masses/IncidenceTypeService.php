<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Masses\IncidenceType;
use App\Repositories\Masses\IncidenceTypeRepository;

class IncidenceTypeService
{
    public function __construct(private readonly IncidenceTypeRepository $incidenceTypes) {}

    public function indexData(string $search): array
    {
        return [
            'incidenceTypes' => $this->incidenceTypes->paginateWithSearch($search),
            'search' => $search,
            'statusOptions' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    public function createIncidenceType(array $data): array
    {
        $incidenceType = $this->incidenceTypes->create($data);
        return $this->incidenceTypeData($incidenceType);
    }

    public function updateIncidenceType(IncidenceType $incidenceType, array $data): array
    {
        $incidenceType = $this->incidenceTypes->update($incidenceType, $data);
        return $this->incidenceTypeData($incidenceType);
    }

    public function deleteIncidenceType(IncidenceType $incidenceType): void
    {
        $this->incidenceTypes->delete($incidenceType);
    }

    private function incidenceTypeData(IncidenceType $incidenceType): array
    {
        return $incidenceType->only(['id', 'name', 'description', 'status']);
    }
}
