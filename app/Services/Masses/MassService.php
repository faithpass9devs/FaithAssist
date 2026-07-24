<?php

namespace App\Services\Masses;

use App\Models\Masses\Mass;
use App\Models\User;
use App\Repositories\Masses\MassRepository;

class MassService
{
    public function __construct(private readonly MassRepository $masses) {}

    public function indexData(User $user, string $search, ?int $weekendId = null): array
    {
        $masses = $this->masses->paginateWeekendGroupsWithScope($user, $search, $weekendId, 5);

        return [
            'masses' => $masses,
            'weekendTotal' => $masses->total(),
            'weekends' => $this->masses->activeWeekends($user),
            'churches' => $this->masses->activeChurches($user),
            'chapels' => $this->masses->activeChapels($user),
            'search' => $search,
            'filters' => [
                'weekend_id' => $weekendId,
            ],
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'mass' => null,
            'weekends' => $this->masses->activeWeekends($user),
            'churches' => $this->masses->activeChurches($user),
            'chapels' => $this->masses->activeChapels($user),
        ];
    }

    public function editFormData(User $user, Mass $mass): array
    {
        $mass->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        return [
            'mass' => $this->masses->serializeMass($mass, true),
            'weekends' => $this->masses->activeWeekends($user),
            'churches' => $this->masses->activeChurches($user),
            'chapels' => $this->masses->activeChapels($user),
        ];
    }

    public function createMass(array $data): Mass
    {
        return $this->masses->create($data);
    }

    public function updateMass(Mass $mass, array $data): Mass
    {
        return $this->masses->update($mass, $data);
    }

    public function deleteMass(Mass $mass): void
    {
        $this->masses->delete($mass);
    }
}
