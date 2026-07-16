<?php

namespace App\Services\Operation;

use App\Models\Operation\Level;
use App\Models\User;
use App\Repositories\Operation\LevelRepository;

class LevelService
{
    public function __construct(private readonly LevelRepository $levels) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'levels' => $this->levels->paginateWithSearch($user, $search),
            'dioceses' => $this->levels->activeDioceses($user),
            'search' => $search,
        ];
    }

    public function createLevel(array $data): array
    {
        $level = $this->levels->create($data);
        return $this->levelData($level);
    }

    public function updateLevel(Level $level, array $data): array
    {
        $level = $this->levels->update($level, $data);
        return $this->levelData($level);
    }

    public function deleteLevel(Level $level): void
    {
        $this->levels->delete($level);
    }

    private function levelData(Level $level): array
    {
        return $level->only(['id', 'diocese_id', 'name', 'description', 'status']);
    }
}
