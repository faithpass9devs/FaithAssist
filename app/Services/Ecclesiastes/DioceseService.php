<?php

namespace App\Services\Ecclesiastes;

use App\Models\Ecclesiastes\Diocese;
use App\Models\User;
use App\Repositories\Ecclesiastes\DioceseRepository;

class DioceseService
{
    public function __construct(private readonly DioceseRepository $dioceses) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'dioceses' => $this->dioceses->paginate($user, $search),
            'states' => $this->dioceses->activeStates($user),
            'search' => $search,
        ];
    }

    public function createDiocese(array $data): array
    {
        $diocese = $this->dioceses->create($data);
        return $this->dioceseData($diocese);
    }

    public function updateDiocese(Diocese $diocese, array $data): array
    {
        $diocese = $this->dioceses->update($diocese, $data);
        return $this->dioceseData($diocese);
    }

    public function deleteDiocese(Diocese $diocese): void
    {
        $this->dioceses->delete($diocese);
    }

    private function dioceseData(Diocese $diocese): array
    {
        return $diocese->only(['id', 'state_id', 'name', 'bishop', 'status']);
    }
}
