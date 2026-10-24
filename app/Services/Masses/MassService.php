<?php

namespace App\Services\Masses;

use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\User;
use App\Repositories\Masses\MassRepository;

class MassService
{
    public function __construct(private readonly MassRepository $masses) {}

    public function indexData(User $user, Weekend $weekend, string $search): array
    {
        return [
            'weekend' => $this->masses->serializeWeekend($weekend),
            'masses' => $this->masses->paginateForWeekend($user, $weekend, $search),
            'search' => $search,
        ];
    }

    public function findVisibleWeekend(User $user, ?int $weekendId): ?Weekend
    {
        return $weekendId ? $this->masses->findVisibleWeekend($user, $weekendId) : null;
    }

    public function createFormData(User $user, ?int $weekendId = null): array
    {
        return [
            'mass' => null,
            'weekendId' => $weekendId && $this->masses->findVisibleWeekend($user, $weekendId) ? $weekendId : null,
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
