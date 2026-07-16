<?php

namespace App\Services\Masses;

use App\Models\Masses\Weekend;
use App\Models\User;
use App\Repositories\Masses\WeekendRepository;

class WeekendService
{
    public function __construct(private readonly WeekendRepository $weekends) {}

    public function indexData(User $user, string $search): array
    {
        $weekends = $this->weekends->paginateWithScope($user, $search);

        return [
            'weekends' => $weekends->through(fn (Weekend $weekend) => $this->weekends->serializeWeekend($weekend)),
            'churches' => $this->weekends->activeChurches($user),
            'search' => $search,
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'weekend' => null,
            'churches' => $this->weekends->activeChurches($user),
        ];
    }

    public function editFormData(User $user, Weekend $weekend): array
    {
        $weekend->load('church:id,name');

        return [
            'weekend' => $this->weekends->serializeWeekend($weekend, true),
            'churches' => $this->weekends->activeChurches($user),
        ];
    }

    public function createWeekend(array $data): Weekend
    {
        return $this->weekends->create($data);
    }

    public function updateWeekend(Weekend $weekend, array $data): Weekend
    {
        return $this->weekends->update($weekend, $data);
    }

    public function deleteWeekend(Weekend $weekend): void
    {
        $this->weekends->delete($weekend);
    }
}
