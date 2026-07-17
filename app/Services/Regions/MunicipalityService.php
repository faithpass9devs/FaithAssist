<?php

namespace App\Services\Regions;

use App\Models\Regions\Municipality;
use App\Models\User;
use App\Repositories\Regions\MunicipalityRepository;

class MunicipalityService
{
    public function __construct(private readonly MunicipalityRepository $municipalities) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'municipalities' => $this->municipalities->paginateWithSearch($user, $search),
            'states' => $this->municipalities->activeStates($user),
            'dioceses' => $this->municipalities->activeDioceses($user),
            'search' => $search,
        ];
    }

    public function createMunicipality(array $data): array
    {
        $municipality = $this->municipalities->create($data);
        return $this->municipalityData($municipality);
    }

    public function updateMunicipality(Municipality $municipality, array $data): array
    {
        $municipality = $this->municipalities->update($municipality, $data);
        return $this->municipalityData($municipality);
    }

    public function deleteMunicipality(Municipality $municipality): void
    {
        $this->municipalities->delete($municipality);
    }

    private function municipalityData(Municipality $municipality): array
    {
        return $municipality->only(['id', 'state_id', 'diocese_id', 'name', 'status']);
    }
}
