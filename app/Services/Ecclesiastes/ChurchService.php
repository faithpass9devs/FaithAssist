<?php

namespace App\Services\Ecclesiastes;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use App\Repositories\Ecclesiastes\ChurchRepository;

class ChurchService
{
    public function __construct(private readonly ChurchRepository $churches) {}

    public function indexData(User $user, string $search, ?int $municipalityId = null, ?int $deaneryId = null, ?string $status = null): array
    {
        $churches = $this->churches->paginateWithFilters($user, $search, $municipalityId, $deaneryId, $status);

        return [
            'churches' => $churches->through(fn (Church $church) => $this->churches->serializeChurch($church)),
            'search' => $search,
            'filters' => [
                'municipality_id' => $municipalityId,
                'deanery_id' => $deaneryId,
                'status' => $status,
            ],
            'municipalities' => $this->churches->activeMunicipalities($user),
            'deaneries' => $this->churches->activeDeaneries($user),
            'statuses' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'church' => null,
            'municipalities' => $this->churches->activeMunicipalities($user),
            'deaneries' => $this->churches->activeDeaneries($user),
            'statuses' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    public function editFormData(User $user, Church $church): array
    {
        $church->loadMissing(['municipality:id,name', 'deanery:id,name']);

        return [
            'church' => $this->churches->serializeChurch($church),
            'municipalities' => $this->churches->activeMunicipalities($user),
            'deaneries' => $this->churches->activeDeaneries($user),
            'statuses' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    public function createChurch(array $data): Church
    {
        return $this->churches->create($data);
    }

    public function updateChurch(Church $church, array $data): Church
    {
        return $this->churches->update($church, $data);
    }

    public function deleteChurch(Church $church): void
    {
        $this->churches->delete($church);
    }
}
