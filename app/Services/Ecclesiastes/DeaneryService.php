<?php

namespace App\Services\Ecclesiastes;

use App\Models\Ecclesiastes\Deanery;
use App\Models\User;
use App\Repositories\Ecclesiastes\DeaneryRepository;

class DeaneryService
{
    public function __construct(private readonly DeaneryRepository $deaneries) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'deaneries' => $this->deaneries->paginate($user, $search),
            'dioceses' => $this->deaneries->activeDioceses($user),
            'search' => $search,
        ];
    }

    public function createDeanery(array $data): array
    {
        $deanery = $this->deaneries->create($data);
        return $this->deaneryData($deanery);
    }

    public function updateDeanery(Deanery $deanery, array $data): array
    {
        $deanery = $this->deaneries->update($deanery, $data);
        return $this->deaneryData($deanery);
    }

    public function deleteDeanery(Deanery $deanery): void
    {
        $this->deaneries->delete($deanery);
    }

    private function deaneryData(Deanery $deanery): array
    {
        return $deanery->only(['id', 'diocese_id', 'name', 'status']);
    }
}
